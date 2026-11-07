<?php

// Перенесення даних між базами різних типів (PostgreSQL на старому сервері → MySQL на сервері Jam).
// Запуск із папки Laravel (там, де artisan):
//   php db-transfer.php export > dump.jsonl      — усі таблиці поточної бази, рядок JSON на запис
//   php db-transfer.php import < dump.jsonl      — у поточну базу: таблиці очищаються й заповнюються наново
// Схему в новій базі створюють міграції (php artisan migrate) ДО імпорту; переносяться лише дані.
// Службові таблиці (migrations, cache, sessions, jobs) не переносяться.

if (! is_file(getcwd().'/artisan')) {
    fwrite(STDERR, "Запускати з папки Laravel (де artisan)\n");
    exit(1);
}
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const SKIP = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

$mode = $argv[1] ?? '';
$db = DB::connection();
$driver = $db->getDriverName();

function tables(string $driver): array
{
    $names = [];
    foreach (Schema::getTables() as $t) {
        // у PostgreSQL лише схема public (без системних)
        if ($driver === 'pgsql' && ($t['schema'] ?? 'public') !== 'public') {
            continue;
        }
        $names[] = $t['name'];
    }
    sort($names);

    return array_values(array_diff($names, SKIP));
}

if ($mode === 'export') {
    $out = fopen('php://stdout', 'w');
    foreach (tables($driver) as $table) {
        $cols = Schema::getColumnListing($table);
        $order = in_array('id', $cols, true) ? 'id' : $cols[0];
        $n = 0;
        fwrite($out, json_encode(['table' => $table], JSON_UNESCAPED_UNICODE)."\n");
        foreach ($db->table($table)->orderBy($order)->lazy(500) as $row) {
            fwrite($out, json_encode(['row' => (array) $row], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
            $n++;
        }
        fwrite(STDERR, sprintf("%-32s %6d\n", $table, $n));
    }
    exit(0);
}

if ($mode !== 'import') {
    fwrite(STDERR, "php db-transfer.php export|import\n");
    exit(1);
}

$existing = array_flip(tables($driver));
$fkOff = fn () => match ($driver) {
    'mysql', 'mariadb' => $db->statement('SET FOREIGN_KEY_CHECKS=0'),
    'sqlite' => $db->statement('PRAGMA foreign_keys = OFF'),
    'pgsql' => $db->statement("SET session_replication_role = 'replica'"),
    default => null,
};
$fkOn = fn () => match ($driver) {
    'mysql', 'mariadb' => $db->statement('SET FOREIGN_KEY_CHECKS=1'),
    'sqlite' => $db->statement('PRAGMA foreign_keys = ON'),
    'pgsql' => $db->statement("SET session_replication_role = 'origin'"),
    default => null,
};
$fkOff();

$table = null;
$cols = [];
$buf = [];
$stats = [];
$failed = 0;

$flush = function () use (&$buf, &$table, &$stats, &$failed, $db) {
    if (! $buf || $table === null) {
        $buf = [];

        return;
    }
    try {
        $db->table($table)->insert($buf);
        $stats[$table] += count($buf);
    } catch (Throwable $e) {
        // порція не вставилась — по одному запису, щоб втратити лише проблемні (і показати їх)
        foreach ($buf as $row) {
            try {
                $db->table($table)->insert($row);
                $stats[$table]++;
            } catch (Throwable $e) {
                $failed++;
                fwrite(STDERR, "  ! $table #".($row['id'] ?? '?').': '.mb_substr($e->getMessage(), 0, 300)."\n");
            }
        }
    }
    $buf = [];
};

$in = fopen('php://stdin', 'r');
while (($line = fgets($in)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (isset($item['table'])) {
        $flush();
        $table = $item['table'];
        if (! isset($existing[$table])) {
            fwrite(STDERR, "  - таблиці $table у новій базі немає, пропускаю\n");
            $table = null;

            continue;
        }
        $cols = array_flip(Schema::getColumnListing($table));
        $db->table($table)->delete();
        $stats[$table] = 0;

        continue;
    }
    if ($table === null) {
        continue;
    }
    $row = array_intersect_key($item['row'], $cols);
    foreach ($row as $k => $v) {
        if (is_bool($v)) {
            $row[$k] = (int) $v;
        } elseif (is_string($v) && preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)(\.\d+)?([+-]\d\d(:?\d\d)?)?$/', $v, $m) && isset($m[3])) {
            // timestamptz PostgreSQL → звичайний час (MySQL не приймає зсув)
            $row[$k] = Illuminate\Support\Carbon::parse($v)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }
    }
    $buf[] = $row;
    if (count($buf) >= 200) {
        $flush();
    }
}
$flush();
$fkOn();

if ($driver === 'pgsql') {
    // послідовності id після вставки з явними id
    foreach (array_keys($stats) as $t) {
        if (Schema::hasColumn($t, 'id')) {
            $db->statement("SELECT setval(pg_get_serial_sequence('\"$t\"', 'id'), COALESCE((SELECT MAX(id) FROM \"$t\"), 1))");
        }
    }
}

foreach ($stats as $t => $n) {
    fwrite(STDERR, sprintf("%-32s %6d\n", $t, $n));
}
fwrite(STDERR, $failed ? "Не перенесено записів: $failed\n" : "Усі записи перенесено\n");
exit($failed ? 2 : 0);
