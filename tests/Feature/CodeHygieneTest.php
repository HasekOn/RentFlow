<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Enums\Role;
use App\Enums\TicketStatus;
use BackedEnum;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Čistota kódu, kterou jde levně hlídat strojově (checklist „Čistota kódu“ ve skillu rentflow-review, vzor JaTime):
 * žádné zbytky ladění a TODO, žádné holé hodnoty rolí a stavů v app/ (vždy enum) a žádné nepoužité třídy.
 * Nepoužité Blade komponenty a partials přibudou s fází 3 (zatím žádné nejsou).
 */
class CodeHygieneTest extends TestCase
{
    /**
     * Adresáře, jejichž třídy Laravel najde sám (auto-discovery), takže je nikdo nezmiňuje jménem.
     */
    private const array AUTO_DISCOVERED = ['app/Policies/', 'app/Listeners/', 'app/Console/Commands/', 'app/Providers/'];

    /**
     * @param  list<string>  $directories  relativně k base_path()
     * @param  list<string>  $suffixes
     * @return list<string> absolutní cesty
     */
    private function files(array $directories, array $suffixes): array
    {
        $found = [];
        foreach ($directories as $directory) {
            if (! is_dir(base_path($directory))) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile() && Str::endsWith($file->getFilename(), $suffixes)) {
                    $found[] = $file->getPathname();
                }
            }
        }
        sort($found);

        return $found;
    }

    private function relative(string $path): string
    {
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $path);
    }

    public function test_no_debug_leftovers_or_todos(): void
    {
        $found = [];
        $files = $this->files(['app', 'config', 'database', 'routes', 'resources/views', 'resources/js', 'resources/css'], ['.php', '.js', '.css']);
        foreach ($files as $path) {
            foreach (file($path) ?: [] as $number => $line) {
                if (preg_match('/\b(TODO|FIXME|XXX)\b|(?<![\w>:$])(dd|dump|ray|var_dump)\(|console\.(log|debug)\(/', $line)) {
                    $found[] = $this->relative($path).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Zbytek ladění nebo TODO – dokonči, nebo smaž.');
    }

    /**
     * Role a stavy (smlouva, platba, nemovitost, tiket) se v app/ píšou enumem, ne řetězcem (2.2): po castu je
     * $payment->status === 'paid' vždy false. Klíče polí ($row['paid'], 'tenant' => …) nevadí.
     *
     * Výjimka: názvy relací tenant a landlord se kryjí s hodnotami Role, proto se nehlásí uvnitř with(), load(),
     * whenLoaded() a compact(). Komentáře se přeskakují.
     */
    public function test_app_has_no_bare_role_or_status_values(): void
    {
        $values = array_unique(array_map(fn (BackedEnum $case): string => (string) $case->value, [
            ...Role::cases(), ...LeaseStatus::cases(), ...PaymentStatus::cases(),
            ...PropertyStatus::cases(), ...TicketStatus::cases(),
        ]));
        $pattern = '/(?<!\[)([\'"])('.implode('|', array_map(fn (string $value) => preg_quote($value, '/'), $values)).')\1(?!\s*(=>|\]))/';
        $relation = '/\b(with|load|whenLoaded|compact)\(/';
        $found = [];

        foreach ($this->files(['app'], ['.php']) as $path) {
            $relative = $this->relative($path);
            if (str_starts_with($relative, 'app/Enums/')) {
                continue;
            }
            foreach (file($path) ?: [] as $number => $line) {
                $code = trim($line);
                if (str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*')) {
                    continue;
                }
                if (preg_match($pattern, $line) && ! preg_match($relation, $line) && ! $this->isRelationListItem($path, $number)) {
                    $found[] = $relative.':'.($number + 1).' '.$code;
                }
            }
        }

        $this->assertSame([], $found, 'Roli a stav piš enumem (Role::Tenant, PaymentStatus::Paid, ->value), ne řetězcem.');
    }

    /**
     * Každá třída v app/ je jménem zmíněná jinde (app, routes, bootstrap, config, database). Mrtvý kód smaž.
     * Výjimka: adresáře s auto-discovery (AUTO_DISCOVERED).
     */
    public function test_every_app_class_is_used(): void
    {
        $sources = implode("\n", array_map(
            fn (string $path): string => (string) file_get_contents($path),
            $this->files(['app', 'routes', 'bootstrap', 'config', 'database'], ['.php']),
        ));
        $unused = [];

        foreach ($this->files(['app'], ['.php']) as $path) {
            $relative = $this->relative($path);
            if (Str::startsWith($relative, self::AUTO_DISCOVERED)) {
                continue;
            }
            $class = basename($relative, '.php');
            // jedna zmínka je deklarace samotné třídy
            if (preg_match_all('/\b'.preg_quote($class, '/').'\b/', $sources) <= 1) {
                $unused[] = $relative;
            }
        }

        $this->assertSame([], $unused, 'Nepoužitá třída – smaž ji, nebo ji zapoj.');
    }

    /**
     * Řádek uvnitř víceřádkového with([ … ]) / load([ … ]): seznam relací, ne hodnota enumu.
     */
    private function isRelationListItem(string $path, int $lineIndex): bool
    {
        static $cache = [];
        $lines = $cache[$path] ??= file($path) ?: [];

        for ($i = $lineIndex - 1; $i >= max(0, $lineIndex - 15); $i--) {
            $previous = trim($lines[$i]);
            if (preg_match('/\b(with|load)\(\[$/', $previous)) {
                return true;
            }
            if (! preg_match('/^[\'"][\w.]+[\'"],?$/', $previous)) {
                return false;
            }
        }

        return false;
    }
}
