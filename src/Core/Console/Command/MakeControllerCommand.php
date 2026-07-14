<?php

namespace App\Core\Console\Command;

class MakeControllerCommand extends Command
{
    public function execute(array $argv): void
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Nom du controller manquant");
            return;
        }

        $name = ucfirst($name);

        $path = __DIR__ . "/../../../../src/Controllers/{$name}.php";

        if (file_exists($path)) {
            $this->error("Le controller existe déjà");
            return;
        }

        $template = file_get_contents(__DIR__ . '/../../../../templates/scaffold/controller.stub');

        $content = str_replace('{{name}}', $name, $template);

        file_put_contents($path, $content);

        $this->success("Controller créé : $name");
    }
}