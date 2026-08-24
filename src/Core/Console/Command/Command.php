<?php

namespace App\Core\Console\Command;

abstract class Command
{
    abstract public function execute(array $argv): void;

    protected function success(string $message): void
    {
        echo "\033[32m$message\033[0m\n";
    }

    protected function error(string $message): void
    {
        echo "\033[31m$message\033[0m\n";
    }
}
