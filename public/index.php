<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Core\Session;

require dirname(__DIR__) . '/src/bootstrap.php';

$config = App::loadConfig();
date_default_timezone_set($config['timezone'] ?? 'America/New_York');

$secure = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
session_name('stagetrack_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => (Request::detectBasePath() ?: '') . '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$app = new App($config, new Session($_SESSION), Request::detectBasePath());
$app->handle(Request::fromGlobals())->send();
