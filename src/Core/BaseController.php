<?php

namespace App\Core;

use App\Core\App;
use App\Helpers\Csrf;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class BaseController
{
    protected ?FilesystemLoader $loader = null;
    protected ?Environment $twig = null;

    public function __construct()
    {

        $this->loader = new FilesystemLoader(__DIR__.'/../Views');
        $this->twig = new Environment($this->loader);
    }

    protected function service(string $id): mixed
    {
        return App::getContainer()->get($id);
    }
    public function render(string $template, array $data = []): string
    {
        $reg = "/^App\\\Controllers\\\(.*)$/";
        preg_match($reg, get_class($this), $matches);
        $className = str_replace('\\', '/', $matches[1]);
        $template = "{$className}/{$template}";
        $data['random'] = rand(); // Variable globale Aléatoire
        $data['csrf'] = Csrf::generate(); // Générer un token CSRF pour chaque rendu
        return $this->twig->render($template, $data);
    }
}
