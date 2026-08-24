<?php

namespace App\Core\Console\Command;

class MakeRepositoryCommand extends Command
{
    public function execute(array $argv): void
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Nom du repository manquant");
            return;
        }

        $name = ucfirst($name);

        $path = __DIR__ . "/../../../../src/Repositories/{$name}Repository.php";

        if (file_exists($path)) {
            $this->error("Le repository existe déjà");
            return;
        }

        $template = file_get_contents(__DIR__ . '/../../../../templates/scaffold/repository.stub');

        $content = str_replace('{{name}}', $name, $template);

        file_put_contents($path, $content);

        $this->success("Repository créé : $name");
    }
}
