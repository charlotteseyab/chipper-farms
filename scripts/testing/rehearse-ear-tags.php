<?php

// Copies the configured database into a NEW isolated schema. Never migrates
// or writes to the source database. Credentials are kept out of output.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception) { fwrite(STDERR, $exception->getMessage().PHP_EOL); exit(1); });
function restoreStatements(PDO $connection, string $path): void
{
    $handle = fopen($path, 'r');
    $statement = '';
    $quote = null;
    $escaped = false;
    while (($line = fgets($handle)) !== false) {
        for ($i = 0, $length = strlen($line); $i < $length; $i++) {
            $char = $line[$i];
            $statement .= $char;
            if ($escaped) { $escaped = false; continue; }
            if ($quote !== null) {
                if ($char === "\\") { $escaped = true; }
                elseif ($char === $quote) { $quote = null; }
            } elseif ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            } elseif ($char === ';') {
                $connection->exec($statement);
                $statement = '';
            }
        }
    }
    fclose($handle);
    if (trim($statement) !== '') throw new RuntimeException('Incomplete backup statement.');
}
$config = config('database.connections.mysql');
$sourceName = $config['database'];
$pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$sourceName}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mode = $argv[1] ?? '';
if ($mode === '--concurrency') {
// Use only the explicitly isolated concurrency database.
$pdo->exec('CREATE DATABASE IF NOT EXISTS `chipper_farms_concurrency_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$env = ($env ?? ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']]) + ['CF_CONCURRENCY_DB_HOST' => $config['host'], 'CF_CONCURRENCY_DB_PORT' => (string) $config['port'], 'CF_CONCURRENCY_DB_USERNAME' => $config['username'], 'CF_CONCURRENCY_DB_PASSWORD' => $config['password']];
$env['DB_DATABASE'] = 'chipper_farms_concurrency_test';
$process = new Symfony\Component\Process\Process([PHP_BINARY, 'vendor/bin/phpunit', '-c', 'phpunit.concurrency.xml', 'tests/Concurrency/EarTagConcurrencyTest.php'], dirname(__DIR__, 2), $env);
$process->setTimeout(180);
$process->run();
echo $process->getOutput(), $process->getErrorOutput();
exit($process->getExitCode());
}
if (str_starts_with($mode, '--verify-backup=')) {
    $dir = substr($mode, strlen('--verify-backup='));
    if (! preg_match('~^/tmp/chipper_farms_tag_rehearsal_[a-f0-9]{8}$~', $dir)) throw new RuntimeException('Unexpected backup path.');
    $targetName = basename($dir);
    $counts = json_decode(file_get_contents($dir.'/counts.json'), true, flags: JSON_THROW_ON_ERROR);
// Verify the snapshot can be restored independently, without erasing either
// the source or migrated copy. The backup is the recovery path, not down().
$restoreName = str_replace('rehearsal', 'restore', $targetName).'_'.bin2hex(random_bytes(3));
$pdo->exec("CREATE DATABASE `{$restoreName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$restore = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$restoreName}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
restoreStatements($restore, $dir.'/backup.sql');
foreach ($counts as $table => $count) {
    if ((int) $restore->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() !== $count) throw new RuntimeException("Restore count mismatch: {$table}");
}
echo "Backup restored to {$restoreName}; every table row count matches the source snapshot.\n";
    exit(0);
}
$targetName = 'chipper_farms_tag_rehearsal_'.bin2hex(random_bytes(4));
$pdo->exec("CREATE DATABASE `{$targetName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$target = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$targetName}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$target->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$dir = sys_get_temp_dir().'/'.$targetName;
mkdir($dir, 0700);
$backup = fopen($dir.'/backup.sql', 'x');
fwrite($backup, "SET FOREIGN_KEY_CHECKS=0;\n");
$counts = [];
foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $entry) {
    $table = $entry[0];
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new RuntimeException('Unexpected table identifier.');
    $ddl = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
    $target->exec($ddl);
    fwrite($backup, $ddl.";\n");
    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $counts[$table] = count($rows);
    foreach ($rows as $row) {
        $columns = implode(',', array_map(fn ($column) => '`'.$column.'`', array_keys($row)));
        $values = implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row)));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$values})";
        $target->exec($sql);
        fwrite($backup, $sql.";\n");
    }
}
$pdo->commit();
fwrite($backup, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($backup);
$target->exec('SET FOREIGN_KEY_CHECKS=1');
file_put_contents($dir.'/counts.json', json_encode($counts, JSON_PRETTY_PRINT));
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $targetName, 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
echo "Source copied read-only to {$targetName}; backup at {$dir}/backup.sql; ".count($counts)." tables.\n";
foreach ([['migrate', '--force'], ['ear-tags:reconcile']] as $args) {
    $process = new Symfony\Component\Process\Process([PHP_BINARY, 'artisan', ...$args], dirname(__DIR__, 2), $env);
    $process->setTimeout(180);
    $process->run();
    echo $process->getOutput();
    if (! $process->isSuccessful()) { echo $process->getErrorOutput(); exit(1); }
}
// Verify the snapshot can be restored independently, without erasing either
// the source or migrated copy. The backup is the recovery path, not down().
$restoreName = str_replace('rehearsal', 'restore', $targetName).'_'.bin2hex(random_bytes(3));
$pdo->exec("CREATE DATABASE `{$restoreName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$restore = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$restoreName}", $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
restoreStatements($restore, $dir.'/backup.sql');
foreach ($counts as $table => $count) {
    if ((int) $restore->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() !== $count) throw new RuntimeException("Restore count mismatch: {$table}");
}
echo "Backup restored to {$restoreName}; every table row count matches the source snapshot.\n";
// Use only the explicitly isolated concurrency database.
$pdo->exec('CREATE DATABASE IF NOT EXISTS `chipper_farms_concurrency_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$env = ($env ?? ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']]) + ['CF_CONCURRENCY_DB_HOST' => $config['host'], 'CF_CONCURRENCY_DB_PORT' => (string) $config['port'], 'CF_CONCURRENCY_DB_USERNAME' => $config['username'], 'CF_CONCURRENCY_DB_PASSWORD' => $config['password']];
$env['DB_DATABASE'] = 'chipper_farms_concurrency_test';
$process = new Symfony\Component\Process\Process([PHP_BINARY, 'vendor/bin/phpunit', '-c', 'phpunit.concurrency.xml', 'tests/Concurrency/EarTagConcurrencyTest.php'], dirname(__DIR__, 2), $env);
$process->setTimeout(180);
$process->run();
echo $process->getOutput(), $process->getErrorOutput();
exit($process->getExitCode());
