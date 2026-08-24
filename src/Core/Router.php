<?php

namespace App\Core;

use App\Core\App;
use App\Core\Exceptions\HttpException;
use App\Helpers\Csrf;
use App\Helpers\FileScanner;
use App\Helpers\Response;
use App\Services\AuthService;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use ReflectionClass;

use function FastRoute\cachedDispatcher;

class Router
{
    private $dispatcher;
    private ?string $csrf;
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
        $this->dispatcher = cachedDispatcher(function (RouteCollector $r) {
            $controllers = FileScanner::globRecursive(__DIR__."/../Controllers/*.php");
            foreach ($controllers as $file) {
                $className = FileScanner::getClassNameFromFile($file);
                if ($className && class_exists($className)) {

                    $reflection = new ReflectionClass($className);
                    $namespace = $reflection->getNamespaceName();
                    $objectGroup = preg_match('#^App\\\\Controllers(?:\\\\([^\\\\]+))?#', $namespace, $matches)
                        ? ($matches[1] ?? null)
                        : null;
                    foreach ($reflection->getMethods() as $method) {
                        $attrs = $method->getAttributes(\App\Core\Attributes\Route::class);
                        $auth = count($method->getAttributes(\App\Core\Attributes\AuthRequired::class)) == 0 ? false : true;
                        foreach ($attrs as $attr) {
                            $route = $attr->newInstance();
                            $path = str_replace('\\', '/', $className) . '/' . $method->getName();
                            $path = str_replace('App/Controllers/', '', $path);
                            $path = $route->path ?? $path;

                            $r->addRoute($route->method, $path, [
                                "class" => $className,
                                "method" => $method->getName(),
                                "short" => $reflection->getShortName(),
                                "objectGroup" => $objectGroup,
                                "auth" => $auth,
                                "api" => $route->api,
                            ]);
                        }
                    }
                }
            }
            $r->addRoute("GET", '', [
                "class" => "App\Controllers\AuthController",
                "method" => "index",
                "public" => true,
                "short" => "AuthController",
                "objectGroup" => null,
                "auth" => false
            ]);
            $r->addRoute("GET", 'dashboard', [
                "class" => "App\Controllers\DashboardController",
                "method" => "index",
                "public" => true,
                "short" => "DashboardController ",
                "objectGroup" => null,
                "auth" => false
            ]);
            $r->addRoute("GET", 'Auth/logout', [
                "class" => "App\Controllers\Auth",
                "method" => "logout",
                "public" => true,
                "short" => "Auth",
                "objectGroup" => null,
                "auth" => false
            ]);
            $r->addRoute("GET", '{file:.+\.js}', [
                "class" => "App\Controllers\JsController",
                "method" => "get",
                "public" => true,
                "short" => "JsController",
                "objectGroup" => null,
                "auth" => false
            ]);
        }, [
            'cacheFile' => __DIR__.'/../../cache/routes.cache',
            'cacheDisabled' => true,
        ]);
    }
    public function dispatch($httpMethod, $uri): void
    {
        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new HttpException(404, 'Route non trouvée');
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new HttpException(405, "Méthode HTTP non autorisée");
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $class = $handler['class'];
                $method = $handler['method'];
                $short = $handler['short'];
                $objectGroup = $handler['objectGroup'];
                $authRequired = $handler['auth'];
                $apiRoute = $handler['api'] ?? false;
                $inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? null;
                if (!$apiRoute && in_array($httpMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $csrf = $inputData['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
                    if (!$csrf || !Csrf::validate($csrf)) {
                        throw new HttpException(403, 'Token CSRF invalide');
                    }

                    if (is_array($inputData)) {
                        unset($inputData['csrf']);
                    }
                }
                if ($authRequired) {
                    $this->auth->verify();
                }
                $input = $routeInfo[2] ?? [];
                if ($inputData) {
                    $input = ['vars' => $inputData];
                }
                $controllerInstance = App::getContainer()->get($class);
                $result = call_user_func([$controllerInstance,$method], $input);
                if ($result instanceof Response && $httpMethod != 'GET') {
                    $result->setCsrf();
                }
                if ($result instanceof Response) {
                    // La classe d'objet App\Object\Classe\ssClasse
                    if ($objectGroup) {    // Classe
                        $result
                            ->setObject($objectGroup)
                            ->setSubObject($short); // ssClasse
                    } else {
                        $result
                            ->setObject($short);
                    }

                    $result
                        ->setAction($method)
                        ->send();
                }
                return;
        }
    }
}
