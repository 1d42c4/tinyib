<?php

declare(strict_types=1);

use TinyIB\Application;
use TinyIB\Config;
use TinyIB\Database;
use TinyIB\Request;
use TinyIB\Session;

require __DIR__ . '/bootstrap.php';
$config = Config::load(__DIR__ . '/settings.php');
date_default_timezone_set($config->timezone);
$request = Request::capture();
if (isset($request->query['csrf'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['token' => Session::token()], JSON_THROW_ON_ERROR);
    exit;
}
Session::validate($request);
$database = new Database($config);
if (PHP_SAPI !== 'cli') {
    ob_start(Session::decorate(...));
}
new Application($config, $database, $request)->run();
