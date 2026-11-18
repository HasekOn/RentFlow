# RentFlow – pokyny pro Claude Code

Správa pronájmu nemovitostí pro tři role: pronajímatel (`landlord`), správce (`manager`) a nájemník (`tenant`).
Obsahuje nemovitosti, smlouvy, platby, tikety, měřiče, inventář, náklady, dokumenty, oznámení, hodnocení a Trust Score.
Laravel 13 · PHP 8.5 · SQLite lokálně (Herd) · Sanctum API `/api/v1` · DomPDF.

**Probíhá přepis frontendu** z React SPA na Blade + Livewire 4 (class-based) + Alpine + Tailwind 4,
stejný stack a vzory jako JaTime. Plán, fáze a checklist jsou v `docs/REWRITE_PLAN.md`. Vždy zjisti, ve které fázi jsme.

## Přechodné období (do konce fáze 5)

- React SPA už v repu není (smazaný v kroku 2.1). **Předloha chování** je `docs/FEATURES.md` a kód na větvi `main`
  (`git show main:frontend/src/pages/<…>.tsx`). Spuštění vedle nového UI přes `git worktree`: skill `rentflow-page`.
- API (`routes/api.php`, `app/Http/Controllers/Api`) a jeho testy jsou **záchranná síť do konce fáze 5**. Tvar JSON
  se kvůli Reactu držet nemusí, ale API testy se neoslabují: když test musí změnit aserci, napiš proč. Jestli API
  zůstane, se rozhodne ve fázi 6.
- Nové UI: `routes/web.php`, `app/Livewire/*`, `resources/views/*`. Česká URL (`/nemovitosti`, `/platby` …).
- Po dokončení kroku ho v `docs/REWRITE_PLAN.md` odškrtni (`- [x]`) a dopiš krátkou poznámku, pokud se něco změnilo.

## Jak spolu pracujeme

- **Komunikace a UI česky**, česká typografie: nezlomitelná mezera v datech („15.&nbsp;10.“), mezi číslem a měnou
  („12&nbsp;500&nbsp;Kč“) a po jednopísmenných předložkách. Kód, názvy tříd a sloupců zůstávají anglicky.
- **Necommituj, nepushuj, nemerguj, nezakládej PR**, pokud o to výslovně nepožádám. Commit dělám já.
  Na konci úkolu dej shrnutí, seznam změněných souborů (po logických krocích) a návrh commit zprávy
  (česky, jeden řádek + pár odrážek).
- Na začátku úkolu `git status` + `git branch --show-current`. Nečistý strom nebo jiná větev → zeptej se.
  Přepis se dělá ve větvi `v2` (nebo `v2-<krok>` z ní; `v2/<krok>` git nedovolí, protože `v2` existuje).
- **Jeden úkol = jeden krok nebo jedna stránka z plánu.** Udělej jen to, co úkol chce, a zastav se.
  U větších kroků nejdřív plán (plan mode), kód až po schválení.
- KISS: žádné nové balíčky, fronty ani JS knihovny mimo ty, které plán výslovně uvádí. Drž se vzorů z repa a z JaTime.
- Po každé změně kódu kontroly (skill `rentflow-checks`); u UI navíc ověření v prohlížeči (skill `rentflow-browser`).
- Vědomá rozhodnutí z plánu nehlas jako chyby; odchylku od plánu napiš a zdůvodni.

## Příkazy

| Co | Příkaz |
| --- | --- |
| Testy | `php artisan test` (filtr: `php artisan test --filter=Payment`; regex s `\|` v PowerShellu nepoužívej) |
| Formát | `vendor/bin/pint --test` (opravit: `vendor/bin/pint <soubor>`) |
| Larastan (`app/`, `phpstan.neon`) | `composer analyse` |
| Pint + Larastan + testy | `composer check` |
| Build nového UI | `npm run build` v kořeni (ne `npm run dev`; appka běží jen přes Herd) |
| Audity | `composer audit`, `npm audit --omit=dev` |

**Lokálně (Windows + Herd):** appka běží na `http://rentflow.test` (PHP 8.5 z Herdu). PHP, Composer a npm pouštěj
přes **PowerShell** (v Git Bash `php` na PATH není). Herd PHP v sandboxu blokuje Inteligentní řízení aplikací
ve Windows → `php`/`composer`/`npm` jsou v `.claude/settings.local.json` vyjmuté ze sandboxu.
`rentflow.test` vrací 502 → zastav se a napiš (problém Windows nebo Herdu, ne appky).
`php artisan migrate:fresh` maže lokální DB → nejdřív záloha (skill `rentflow-browser`, `db-snapshot.php`).
`composer require` s `^` ve Windows nefunguje (cmd znak spolkne) → piš `~13.0` nebo uprav `composer.json` ručně.

## Role a oprávnění

- `users.role` je enum `App\Enums\Role` (cast), dotazuj se přes `isLandlord()` / `isManager()` / `isTenant()`.
  `role` a `trust_score` nejsou fillable: `User::create(['role' => …])` roli **tiše zahodí** (uživatel bude
  `tenant`), nastavuj je přes `forceFill()`. Registrace přijme jen `landlord` nebo `tenant`.
- Statusy a typy jsou enumy v `app/Enums` s castem na modelu. Po castu je `$model->status === 'paid'` vždy `false`:
  porovnávej s enumem (`=== PaymentStatus::Paid`, `in_array(…, true)`, `match` na casech), validuj `Rule::enum()`.
- **Specifikace oprávnění = matice v `README.md` (sekce „Role a oprávnění“).** Když se kód s maticí rozchází,
  nehádej a zeptej se.
- Pronajímatel vidí a spravuje jen **své** nemovitosti (`properties.landlord_id`). Správce jen **přiřazené**
  (`property_manager`) a může být zároveň nájemníkem jinde. Nájemník vidí jen nemovitosti s **aktivní** smlouvou.
- Policies jsou v `app/Policies` (Property, Lease, Payment, Ticket, Expense, Notice). Ostatní modely (Document, Meter,
  MeterReading, InventoryItem, Rating, User) policy zatím nemají a doplní se ve fázi 2.

## Kód – vzory a gotchas

- **Byznys logika** patří do `app/Actions/<Oblast>/<Sloveso><Věc>.php` (jedna veřejná `handle()`, transakce
  a eventy uvnitř). Volají ji API controllery i Livewire, autorizace je u volajícího. Services
  (`TrustScoreService`, `BankImportService`) zůstávají. Postup: skill `rentflow-backend`.
- **Co kdo vidí** řeší jeden scope na modelu `visibleTo(User $user)` (vzor `Property::visibleTo`). Používá ho API
  `index` i Livewire seznamy. Nepiš `if ($user->role === …)` větvení do controllerů ani komponent.
- **Livewire:** autorizace (policy) v **každé** akci, ne jen v `mount()` nebo routě. Route middleware na update
  endpointu neplatí a persistent middleware neběží v `Livewire::test()`. Validuj všechno, co přijde z klienta.
  Id, která klient nesmí měnit, označ `#[Locked]`. Sdílená logika komponent patří do traitů `app/Livewire/Concerns/`.
- `find()` + `if ($x === null) { abort(404); }`, ne `findOrFail()` / `abort_if()` (Larastan, test harness).
- `Model::shouldBeStrict()` mimo produkci → eager loading všude. Model se sloupcem s DB defaultem
  má `$attributes` se stejnými výchozími hodnotami.
- Blade: v `@props` / `@php` nepiš `<x-…>` ani v komentáři. Výstup jen přes `{{ }}`, `{!! !!}` jen u důvěryhodného
  HTML. Používej design systém `<x-…>` (z JaTime, fáze 3) a nové komponenty zakládej, jen když žádná nesedí.
- Dialogy `<x-modal>`. `$store.confirm` uvnitř `<x-modal>` nefunguje → potvrzení druhým kliknutím. Po úspěchu
  zavírej modal serverovým eventem, ne optimisticky.
- Alpine: `x-show` přepíná až v dalším snímku. Když hned potom fokusuješ, použij `x-bind:hidden`.
- **Soubory:** dokumenty k nemovitostem jsou citlivé. Nikdy je nevystavuj přes veřejnou URL, stahují se jen
  autorizovanou routou. Upload: `mimes` + `max`, žádné SVG/HTML od uživatelů.
- Peníze: sloupce `decimal`. Součty počítej v DB (`SUM`) nebo v haléřích (`int`), nikdy přes float.
  Formát „12&nbsp;500&nbsp;Kč“ přes jeden helper nebo komponentu (`<x-money>`).
- Data: SQLite ukládá datum s časem → rozsahy `>=` a `<` (ne `<=` konec dne). Časová zóna `Europe/Prague`.
- Kód nesmí záviset na konkrétní DB (lokálně SQLite, produkce zatím nerozhodnutá, README počítá s MariaDB).
- `env()` jen v `config/*.php`. Novou proměnnou prostředí výslovně nahlas.
- Pint: `! $x` s mezerou. V docblocku nepiš `@slovo` na začátku slova (Pint to bere jako anotaci).
- Scheduler (`routes/console.php`): generování plateb (1. v měsíci), po splatnosti (denně) a končící smlouvy (denně).
  Notifikace jdou do DB a e-mailem přes frontu `database`.
- Listenery se registrují **jen auto-discovery** z `app/Listeners` podle typu události v `handle(Event $e)`
  (výchozí Laravel 13, jako JaTime). Nepiš `$listen` ani vlastní `EventServiceProvider`, jinak běží každý listener
  dvakrát. Kontrola `php artisan event:list`, hlídá `EventListenersTest`.

## Testy

- `tests/Feature` (API přes `$this->apiUrl('/…')`), nové Livewire testy v `tests/Feature/Livewire/` přes
  `Livewire::test()`. `tests/Unit` (TrustScore). Testy běží na SQLite `:memory:`.
- Každá akce pokrývá: jinou roli (403), cizí data (jiný pronajímatel), nepřiřazeného správce, nájemníka bez aktivní
  smlouvy, `#[Locked]`, validaci a počet dotazů nezávislý na množství dat.
- Pomocné metody v testech nepojmenovávej jako metody `TestCase` (`post()`, `component()` …).
- Po 404 nejde stejnou `Testable` dál volat. `Livewire::withQueryParams()` platí i pro další `test()`.

## Kde co je

- `app/Http/Controllers/Api/*` (API), `app/Policies/*`, `app/Services/*`, `app/Traits/Filterable.php`,
  `app/Console/Commands/*` (scheduler), `app/Events` + `app/Listeners` + `app/Notifications`.
- Od fáze 2: `app/Actions/*`, `app/Enums/*`. Od fáze 3: `app/Livewire/*`, `resources/views/components/*`
  (design systém), `resources/css/app.css` (`@theme` tokeny), `app/Support/helpers.php` (`cz_*`).
- Předloha pro přepis: `docs/FEATURES.md` (inventář funkcí, fáze 1) a React na větvi `main`
  (`git show main:frontend/src/pages/…`). `docs/REVIEW.md` je audit (fáze 1).
- `lang/cs/*` + `lang/cs.json` (laravel-lang), `phpstan.neon` (+ `phpstan-baseline.neon`), `pint.json`.
- Demo účty (`php artisan migrate:fresh --seed`, heslo `password`): `landlord@rentflow.cz` (pronajímatel),
  `manager@rentflow.cz` (správce), `marie@rentflow.cz`, `tomas@rentflow.cz` (nájemníci). Další jsou v README.

## Skills v `.claude/skills/`

`rentflow-step` (jeden krok z plánu od stavu repa po shrnutí, spouští uživatel) · `rentflow-checks` (kontroly,
subagent – v argumentu co se změnilo) · `rentflow-browser` (proklikání, útoky, měření, záloha DB) ·
`rentflow-review` (review větve před mergem, subagent, spouští uživatel) · `rentflow-backend` (změna logiky:
policy, Action, scope, testy) · `rentflow-page` (převod React stránky na Livewire).
Další skills (design, security, release, dlouhé texty) jsou naplánované ve fázích v `docs/REWRITE_PLAN.md`.
