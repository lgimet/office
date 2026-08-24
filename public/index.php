<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Container;
use App\Core\ErrorHandler;
use App\Helpers\Csrf;
use Dotenv\Dotenv;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Csrf::generate();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

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
