<?php

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\RedirectException;
use App\Helpers\Response;
use Throwable;

class ErrorHandler
{
    public function handle(Throwable $exception): void
    {
        if ($exception instanceof RedirectException) {
            http_response_code($exception->getStatusCode());
            header('Location: ' . $exception->getLocation());
            return;
        }

        $this->log($exception);

        if ($exception instanceof HttpException) {
            $this->emitHttpError($exception);
            return;
        }

        $message = $this->isDebug()
            ? $exception->getMessage()
            : 'Une erreur interne est survenue.';

        $this->emitHttpError(new HttpException(500, $message, 0, $exception));
    }

    private function emitHttpError(HttpException $exception): void
    {
        http_response_code($exception->getStatusCode());

        if ($this->expectsJson()) {
            echo (new Response())
                ->setError(
                    $exception->getStatusCode(),
                    $exception->getMessage(),
                    $exception->getUnderCode()
                )
                ->toJson();
            return;
        }

        header('Content-Type: text/html; charset=utf-8');

        $statusCode = $exception->getStatusCode();
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erreur {$statusCode}</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; color: #222; }
        main { max-width: 720px; margin: 10vh auto; padding: 32px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        h1 { margin-top: 0; font-size: 32px; }
        p { line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <h1>Erreur {$statusCode}</h1>
        <p>{$message}</p>
    </main>
</body>
</html>
HTML;
    }

    private function expectsJson(): bool
    {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return str_contains($accept, 'application/json')
            || strcasecmp($requestedWith, 'XMLHttpRequest') === 0
            || str_ends_with(parse_url($uri, PHP_URL_PATH) ?: '', '.js');
    }

    private function isDebug(): bool
    {
        return ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    }

    private function log(Throwable $exception): void
    {
        error_log(sprintf(
            '[%s] %s: %s in %s:%d%s%s',
            date('c'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL,
            $exception->getTraceAsString()
        ));
    }
}
