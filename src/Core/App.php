<?php

namespace App\Core;

use RuntimeException;

class App
{
    private static ?Container $container = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    public static function getContainer(): Container
    {
        if (self::$container === null) {
            throw new RuntimeException('Container non initialisé.');
        }

        return self::$container;
    }
}
