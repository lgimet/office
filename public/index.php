<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Container;
use App\Core\ErrorHandler;
use App\Helpers\Csrf;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if (session_status() === PHP_SESSION_NONE) {
    $sessionTtl = filter_var($_ENV['OFFICE_SESSION_TTL'] ?? null, FILTER_VALIDATE_INT);
    if ($sessionTtl === false || $sessionTtl <= 0) throw new RuntimeException('OFFICE_SESSION_TTL doit être un entier positif.');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)$sessionTtl);
    session_name((string)($_ENV['OFFICE_SESSION_COOKIE_NAME'] ?? 'office_session'));
    session_set_cookie_params(['lifetime'=>$sessionTtl,'path'=>'/','secure'=>($_ENV['APP_ENV'] ?? 'prod') === 'prod' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
Csrf::generate();

if ($_ENV['APP_DEBUG'] === 'true') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}
try {
    $container = new Container();
    $servicesConfigurator = require __DIR__ . '/../config/services.php';
    $servicesConfigurator($container);
    App::setContainer($container);

    $router = $container->get(\App\Core\Router::class);

    $router->dispatch(
        $_SERVER['REQUEST_METHOD'],
        trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')
    );
} catch (\Throwable $exception) {
    (new ErrorHandler())->handle($exception);
}
