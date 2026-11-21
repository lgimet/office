<?php

declare(strict_types=1);

namespace App\Controller;

use Twig\Environment;

final class PreviewController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function unlock(): string
    {
        $providedKey = trim((string) ($_GET['key'] ?? ''));
        $expectedKey = $this->env('SITE_PREVIEW_KEY');

        if ($providedKey === '' || $expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            http_response_code(403);

            return 'Clé de prévisualisation invalide.';
        }

        $cookieName = $this->env('SITE_PREVIEW_COOKIE_NAME', 'spotcard_preview');
        $cookieDays = max(1, (int) $this->env('SITE_PREVIEW_COOKIE_DAYS', '30'));
        $cookieValue = hash_hmac('sha256', $expectedKey, $this->env('APP_SECRET'));
        $isHttps = $this->isHttps();

        setcookie($cookieName, $cookieValue, [
            'expires' => time() + ($cookieDays * 86400),
            'path' => '/',
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax',
        ]);

        header('Location: /', true, 302);

        return '';
    }

    private function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    private function isHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return ($_SERVER['SERVER_PORT'] ?? null) === '443';
    }
}
