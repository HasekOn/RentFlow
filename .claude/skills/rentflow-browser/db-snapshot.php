<?php

/**
 * Záloha a obnova lokální SQLite DB před ručním testováním (migrate:fresh, demo data).
 *
 * Spouštěj z kořene repa přes PowerShell (PHP z Herdu, mimo sandbox):
 *   php .claude/skills/rentflow-browser/db-snapshot.php backup    # database/database.sqlite → .bak
 *   php .claude/skills/rentflow-browser/db-snapshot.php restore   # .bak → database/database.sqlite, .bak smaže
 *   php .claude/skills/rentflow-browser/db-snapshot.php check     # integrita, režim, počty, poslední migrace
 *
 * DB běží ve WAL. Před kopií se proto WAL přelije do hlavního souboru (wal_checkpoint TRUNCATE) – jinak by
 * záloha neobsahovala poslední zápisy, nebo by se při obnově do vrácené DB přehrál WAL testovací DB.
 * Přepínač --force přepíše existující zálohu, --keep nechá zálohu po obnově.
 */
$db = 'database/database.sqlite';
$bak = $db.'.bak';
$action = $argv[1] ?? 'check';
$flags = array_slice($argv, 2);

if (! is_file('artisan')) {
    fwrite(STDERR, "Spusť z kořene repa (C:\\dev\\rentflow).\n");
    exit(1);
}

$open = fn (string $path): PDO => new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$checkpoint = function (string $path) use ($open): void {
    $pdo = $open($path);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchAll();
};

$integrity = fn (string $path): string => (string) $open($path)->query('PRAGMA integrity_check')->fetchColumn();

switch ($action) {
    case 'backup':
        if (is_file($bak) && ! in_array('--force', $flags, true)) {
            fwrite(STDERR, "Záloha $bak už existuje – nejdřív ji obnov (restore), nebo přidej --force.\n");
            exit(1);
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
        $checkpoint($db); // WAL testovací DB do jejího souboru, ať se nepřehraje do obnovené
        copy($bak, $db) || exit(1);
        foreach ([$db.'-wal', $db.'-shm'] as $side) {
            if (is_file($side) && ! @unlink($side)) {
                echo "Upozornění: $side drží otevřený PHP-FPM (Herd) – po checkpointu je prázdný, nevadí.\n";
            }
        }
        $result = $integrity($db);
        if ($result !== 'ok') {
            fwrite(STDERR, "Obnova se nepovedla (integrita: $result) – záloha $bak zůstává.\n");
            exit(1);
        }
        if (! in_array('--keep', $flags, true)) {
            unlink($bak);
        }
        echo "Obnoveno z $bak (integrita: $result)".(in_array('--keep', $flags, true) ? ', záloha ponechána' : ', záloha smazána').".\n";
        break;

    case 'check':
        $pdo = $open($db);
        echo 'integrita: ', $pdo->query('PRAGMA integrity_check')->fetchColumn(), PHP_EOL;
        echo 'journal_mode: ', $pdo->query('PRAGMA journal_mode')->fetchColumn(), PHP_EOL;
        foreach (['users', 'properties', 'leases', 'payments', 'tickets'] as $table) {
            $exists = $pdo->query("select count(*) from sqlite_master where type = 'table' and name = '$table'")->fetchColumn();
            echo $table, ': ', $exists ? $pdo->query("select count(*) from $table")->fetchColumn() : '–', PHP_EOL;
        }
        echo 'poslední migrace: ', $pdo->query('select migration from migrations order by id desc limit 1')->fetchColumn(), PHP_EOL;
        echo 'záloha: ', is_file($bak) ? $bak.' ('.filesize($bak).' B)' : 'není', PHP_EOL;
        break;

    default:
        fwrite(STDERR, "Použití: php .claude/skills/rentflow-browser/db-snapshot.php backup|restore|check [--force|--keep]\n");
        exit(1);
}
