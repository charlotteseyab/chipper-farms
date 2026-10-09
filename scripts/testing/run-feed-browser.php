<?php

// Disposable SQLite database, isolated HTTP server and real Chrome walkthrough.
require __DIR__.'/../../vendor/autoload.php';
$root = dirname(__DIR__, 2);
$dir = sys_get_temp_dir().'/feed-browser-'.bin2hex(random_bytes(4));
mkdir($dir, 0700);
$db = $dir.'/chipper_farms_upgrade_test'; touch($db);
$env = ['APP_ENV' => 'local', 'APP_URL' => 'http://127.0.0.1:8917', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'SESSION_SECURE_COOKIE' => 'false', 'APP_CONFIG_CACHE' => $dir.'/config.php'];
$migrate = new Symfony\Component\Process\Process([PHP_BINARY, 'artisan', 'migrate', '--force'], $root, $env);
$migrate->setTimeout(180); $migrate->run();
if (! $migrate->isSuccessful()) { echo $migrate->getOutput(), $migrate->getErrorOutput(); exit(1); }
$server = new Symfony\Component\Process\Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port=8917', '--no-reload'], $root, $env);
$server->start();
try {
    for ($i = 0; $i < 40; $i++) {
        $socket = @fsockopen('127.0.0.1', 8917, $errno, $error, 0.1);
        if ($socket) { fclose($socket); break; }
        usleep(100000);
    }
    $test = new Symfony\Component\Process\Process([PHP_BINARY, 'vendor/bin/phpunit', '--no-configuration', '--bootstrap', 'vendor/autoload.php', 'tests/Browser/FeedWorkflowTest.php'], $root, $env);
    $test->setTimeout(180); $test->run();
    echo $test->getOutput(), $test->getErrorOutput(), "\nBrowser database: {$db}\n";
    $exitCode = $test->getExitCode();
} finally { $server->stop(); }
exit($exitCode);
