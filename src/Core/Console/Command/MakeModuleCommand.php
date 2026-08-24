<?php

namespace App\Core\Console\Command;

class MakeModuleCommand extends Command
{
    public function execute(array $argv): void
    {
        $name = $argv[2] ?? null;

        if (!$name) {
            $this->error("Nom du module manquant");
            return;
        }

        $name = ucfirst($name);

        $this->make('Controllers', $name);
        $this->make('Services', $name);
        $this->make('Repositories', $name);
        $this->makeJs($name);

        $this->success("Module complet créé : $name");
    }

    private function make(string $type, string $name): void
    {
        $aType = [
            'Controllers' => '',
            'Services' => 'Service',
            'Repositories' => 'Repository'
        ];
        $templatePath = __DIR__ . "/../../../../templates/scaffold/" . strtolower($type) . ".stub";
        $targetPath = __DIR__ . "/../../../../src/{$type}/{$name}{$aType[$type]}.php";

        if (file_exists($targetPath)) {
            $this->error("$type déjà existant");
            return;
        }

        $template = file_get_contents($templatePath);
        $content = str_replace('{{name}}', $name, $template);

        file_put_contents($targetPath, $content);

        echo "$type créé\n";
    }
    private function makeJs(string $name): void
    {
        $templatePath = __DIR__ . "/../../../../templates/scaffold/js.stub";
        $targetPath = __DIR__ . "/../../../../public/assets/js/Object/{$name}.js";

        if (file_exists($targetPath)) {
            $this->error("JS module déjà existant");
            return;
        }

        $template = file_get_contents($templatePath);
        $content = str_replace('{{name}}', $name, $template);

        file_put_contents($targetPath, $content);

        echo "JS module créé\n";
    }
}
