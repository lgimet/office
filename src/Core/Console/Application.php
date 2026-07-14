<?php

namespace App\Core\Console;

class Application
{
    private array $commands = [];

    public function __construct()
    {
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $this->commands = [
            'make:controller' => \App\Core\Console\Command\MakeControllerCommand::class,
            'make:service'    => \App\Core\Console\Command\MakeServiceCommand::class,
            'make:repository' => \App\Core\Console\Command\MakeRepositoryCommand::class,
            'make:module' => \App\Core\Console\Command\MakeModuleCommand::class,
            'user:create-admin' => \App\Core\Console\Command\CreateAdminUserCommand::class,
        ];
    }

    public function run(array $argv): void
    {
        $commandName = $argv[1] ?? null;

        if (!$commandName) {
            echo "Aucune commande\n";
            return;
        }

        if (!isset($this->commands[$commandName])) {
            echo "Commande inconnue\n";
            return;
        }

        $commandClass = $this->commands[$commandName];
        $command = new $commandClass();

        $command->execute($argv);
    }
}
