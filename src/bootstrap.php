<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('StageTrack needs PHP 8.2 or newer.');
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (! str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/helpers.php';
