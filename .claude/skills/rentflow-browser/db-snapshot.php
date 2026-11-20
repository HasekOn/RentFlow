<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Záloha a obnova lokální SQLite DB před ručním testováním (migrate:fresh, demo data).
 *
 * Spouštěj z kořene repa přes PowerShell (PHP z Herdu, mimo sandbox):
 *   php .claude/skills/rentflow-browser/db-snapshot.php backup        # aktivní DB → .bak
 *   php .claude/skills/rentflow-browser/db-snapshot.php restore       # .bak → aktivní DB, .bak smaže
 *   php .claude/skills/rentflow-browser/db-snapshot.php check         # integrita, režim, počty, poslední migrace
 *   php .claude/skills/rentflow-browser/db-snapshot.php save-default  # aktivní DB → database/default.sqlite
 *   php .claude/skills/rentflow-browser/db-snapshot.php load-default  # database/default.sqlite → aktivní DB
 *
 * Aktivní DB = soubor, se kterým appka opravdu běží (config database.connections.sqlite.database, tedy
 * DB_DATABASE z .env) – ne natvrdo database/database.sqlite. Jinak by migrate:fresh při testu přemazal jinou DB,
 * než jakou skript zálohuje. database/default.sqlite jsou výchozí demo data (migrate:fresh --seed, případně
 * doplněná), ke kterým se jde kdykoli vrátit. Je v gitignore (database/.gitignore) a do repa nepatří.
 *
 * Lokálně je teď journal_mode delete, ve WAL (produkce, jiná konfigurace) by ale záloha bez checkpointu
 * neobsahovala poslední zápisy, nebo by se při obnově do vrácené DB přehrál WAL testovací DB. Proto se před
 * kopií vždy dělá wal_checkpoint(TRUNCATE) – v režimu delete nic neudělá.
 * Přepínač --force přepíše existující zálohu, --keep nechá zálohu po obnově.
 */
if (! is_file('artisan')) {
    fwrite(STDERR, "Spusť z kořene repa (C:\\dev\\rentflow).\n");
    exit(1);
}

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$configured = (string) config('database.connections.sqlite.database');
$db = realpath($configured) ?: realpath(base_path($configured));

if (config('database.default') !== 'sqlite' || $db === false) {
    fwrite(STDERR, 'Aktivní DB není existující SQLite soubor (database.default = '.config('database.default').", cesta: $configured).\n");
    exit(1);
}

$bak = $db.'.bak';
$default = database_path('default.sqlite');
$action = $argv[1] ?? 'check';
$flags = array_slice($argv, 2);

$open = fn (string $path): PDO => new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$checkpoint = function (string $path) use ($open): void {
    $pdo = $open($path);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchAll();
};

$integrity = fn (string $path): string => (string) $open($path)->query('PRAGMA integrity_check')->fetchColumn();

/** Přepíše aktivní DB kopií $from (WAL testovací DB se nejdřív přelije, ať se nepřehraje do obnovené). */
$replace = function (string $from) use ($db, $checkpoint, $integrity): string {
    $checkpoint($db);
    copy($from, $db) || exit(1);
    foreach ([$db.'-wal', $db.'-shm'] as $side) {
        if (is_file($side) && ! @unlink($side)) {
            echo "Upozornění: $side drží otevřený PHP-FPM (Herd) – po checkpointu je prázdný, nevadí.\n";
        }
    }

    return $integrity($db);
};

echo "Aktivní DB: $db\n";

switch ($action) {
    case 'backup':
        if (is_file($bak) && ! in_array('--force', $flags, true)) {
            // nenulový kód: další krok přípravy (load-default, migrate:fresh) se nesmí spustit bez vlastní zálohy
            fwrite(STDERR, 'CHYBA: záloha už existuje ('.date('j. n. Y H:i', (int) filemtime($bak)).", $bak) – nejdřív restore nebo smaž (může patřit jiné session; přepsat jen --force). Nic dalšího nespouštěj.\n");
            exit(2);
        }
        $checkpoint($db);
        copy($db, $bak) || exit(1);
        echo "Záloha: $bak (".filesize($bak)." B, integrita: {$integrity($bak)})\n";
        break;

    case 'restore':
        if (! is_file($bak)) {
            fwrite(STDERR, "Záloha $bak neexistuje.\n");
            exit(1);
        }
        $result = $replace($bak);
        if ($result !== 'ok') {
            fwrite(STDERR, "Obnova se nepovedla (integrita: $result) – záloha $bak zůstává.\n");
            exit(1);
        }
        if (! in_array('--keep', $flags, true)) {
            unlink($bak);
        }
        echo "Obnoveno z $bak (integrita: $result)".(in_array('--keep', $flags, true) ? ', záloha ponechána' : ', záloha smazána').".\n";
        break;

    case 'save-default':
        $checkpoint($db);
        copy($db, $default) || exit(1);
        echo "Výchozí data uložena: $default (".filesize($default)." B, integrita: {$integrity($default)})\n";
        break;

    case 'load-default':
        if (! is_file($default)) {
            fwrite(STDERR, "Výchozí data $default neexistují – nejdřív migrate:fresh --seed a save-default.\n");
            exit(1);
        }
        $result = $replace($default);
        echo "Načtena výchozí data z $default (integrita: $result).\n";
        exit($result === 'ok' ? 0 : 1);

    case 'check':
        $pdo = $open($db);
        echo 'integrita: ', $pdo->query('PRAGMA integrity_check')->fetchColumn(), PHP_EOL;
        echo 'journal_mode: ', $pdo->query('PRAGMA journal_mode')->fetchColumn(), PHP_EOL;
        foreach (['users', 'properties', 'leases', 'payments', 'tickets', 'documents'] as $table) {
            $exists = $pdo->query("select count(*) from sqlite_master where type = 'table' and name = '$table'")->fetchColumn();
            echo $table, ': ', $exists ? $pdo->query("select count(*) from $table")->fetchColumn() : '–', PHP_EOL;
        }
        echo 'poslední migrace: ', $pdo->query('select migration from migrations order by id desc limit 1')->fetchColumn(), PHP_EOL;
        echo 'záloha: ', is_file($bak) ? $bak.' ('.date('j. n. Y H:i', (int) filemtime($bak)).', '.filesize($bak).' B)' : 'není', PHP_EOL;
        echo 'výchozí data: ', is_file($default) ? $default.' ('.filesize($default).' B)' : 'nejsou', PHP_EOL;
        break;

    default:
        fwrite(STDERR, "Použití: php .claude/skills/rentflow-browser/db-snapshot.php backup|restore|check|save-default|load-default [--force|--keep]\n");
        exit(1);
}
