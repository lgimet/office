<?php

namespace App\Core\Console\Command;

class MakeServiceCommand extends Command
{
    public function execute(array $argv): void
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Nom du service manquant");
            return;
        }

        $name = ucfirst($name);

        $path = __DIR__ . "/../../../../src/Services/{$name}Service.php";

        if (file_exists($path)) {
            $this->error("Le service existe déjà");
            return;
        }

        $template = file_get_contents(__DIR__ . '/../../../../templates/scaffold/service.stub');

        $content = str_replace('{{name}}', $name, $template);

        file_put_contents($path, $content);

        $this->success("Service créé : $name");
    }
}