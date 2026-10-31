<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$envFile = dirname(__DIR__).'/.env';
if (method_exists(Dotenv::class, 'bootEnv') && is_file($envFile)) {
    (new Dotenv())->bootEnv($envFile);
} else {
    $_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? 'test';
    $_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ?? '1';
    $testEnvFile = $envFile.'.'.$_SERVER['APP_ENV'];
    if (is_file($testEnvFile)) {
        (new Dotenv())->load($testEnvFile);
    }
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}
