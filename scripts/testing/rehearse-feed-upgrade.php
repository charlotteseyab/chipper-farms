<?php

// Read-only source snapshot; all migration work uses a newly created schema.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception) { fwrite(STDERR, $exception->getMessage().PHP_EOL); exit(1); });
$config = config('database.connections.mysql');
$sourceName = $config['database'];
$connect = fn ($name) => new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$name}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$source = $connect($sourceName);
if (($argv[1] ?? '') === '--concurrency') {
    $source->exec('CREATE DATABASE IF NOT EXISTS `chipper_farms_concurrency_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $env = ['CF_CONCURRENCY_DB_HOST' => $config['host'], 'CF_CONCURRENCY_DB_PORT' => (string) $config['port'], 'CF_CONCURRENCY_DB_USERNAME' => $config['username'], 'CF_CONCURRENCY_DB_PASSWORD' => $config['password']];
    $process = new Symfony\Component\Process\Process([PHP_BINARY, 'vendor/bin/phpunit', '-c', 'phpunit.concurrency.xml', 'tests/Concurrency/FeedWorkflowConcurrencyTest.php'], dirname(__DIR__, 2), $env);
    $process->setTimeout(180); $process->run();
    echo $process->getOutput(), $process->getErrorOutput(); exit($process->getExitCode());
}
$name = 'chipper_farms_feed_rehearsal_'.bin2hex(random_bytes(4));
$source->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$copy = $connect($name);
$copy->exec('SET FOREIGN_KEY_CHECKS=0');
$source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$original = [];
foreach ($source->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new RuntimeException('Invalid identifier');
    $copy->exec($source->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1]);
    $rows = $source->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $columns = array_column($source->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $original[$table] = ['columns' => $columns, 'rows' => $rows];
    foreach ($rows as $row) {
        $fields = implode(',', array_map(fn ($field) => '`'.$field.'`', array_keys($row)));
        $values = implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $source->quote((string) $value), $row));
        $copy->exec("INSERT INTO `{$table}` ({$fields}) VALUES ({$values})");
    }
}
$source->commit(); $copy->exec('SET FOREIGN_KEY_CHECKS=1');
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $name, 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
$path = 'database/migrations/2028_10_14_000000_extend_feed_planning_and_history.php';
// Deliberately isolate the feed migration from the previously documented tag
// migration blockers. This is not a rehearsal of the complete deployment.
foreach ([['migrate', '--path='.$path, '--force'], ['migrate:rollback', '--path='.$path, '--step=1', '--force'], ['migrate', '--path='.$path, '--force']] as $args) {
    $process = new Symfony\Component\Process\Process([PHP_BINARY, 'artisan', ...$args], dirname(__DIR__, 2), $env);
    $process->setTimeout(180); $process->run();
    echo $process->getOutput(), $process->getErrorOutput();
    if (! $process->isSuccessful()) exit(1);
    foreach ($original as $table => $snapshot) {
        if ($table === 'migrations') continue;
        $fields = implode(',', array_map(fn ($field) => '`'.$field.'`', $snapshot['columns']));
        $rows = $copy->query("SELECT {$fields} FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        // Loose value comparison tolerates wider decimal display precision.
        if ($rows != $snapshot['rows']) throw new RuntimeException("Existing values changed in {$table}");
    }
}
echo count($original)." source tables copied; existing values unchanged after up/down/up. Source was not migrated. Isolated schema: {$name}\n";
