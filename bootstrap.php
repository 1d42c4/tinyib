<?php

declare(strict_types=1);

use TinyIB\BoardMessage;
use TinyIB\Session;

if (PHP_VERSION_ID < 80500) {
    http_response_code(500);
    exit('TinyIB requires PHP 8.5 or newer.');
}
chdir(__DIR__);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'TinyIB\\')) {
        $file = __DIR__ . '/app/' . substr($class, 7) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $exception): void {
    if (!$exception instanceof BoardMessage) {
        error_log((string) $exception);
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code($exception instanceof BoardMessage ? $exception->status : 500);
    header('Content-Type: text/html; charset=utf-8');
    $message = $exception instanceof BoardMessage ? $exception->getMessage() : 'The request could not be completed. Check the PHP error log.';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>TinyIB</title><body><p>' . $message . '</p><p><a href="javascript:history.back()">Go back</a></p></body></html>';
});
foreach (['gd', 'fileinfo', 'mbstring', 'pdo', 'curl'] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException("The PHP $extension extension is required.");
    }
}
Session::start();
