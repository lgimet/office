<?php

namespace App\Core\Console\Command;

use App\Repositories\AuthRepository;

class CreateAdminUserCommand extends Command
{
    public function execute(array $argv): void
    {
        $email = strtolower(trim($this->prompt('E-mail administrateur : ')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Adresse e-mail invalide.');
            return;
        }

        $password = $this->promptHidden('Mot de passe : ');
        $confirmation = $this->promptHidden('Confirmez le mot de passe : ');

        if ($password === '' || $password !== $confirmation) {
            $this->error('Les mots de passe sont vides ou ne correspondent pas.');
            return;
        }

        $repository = new AuthRepository();
        if ($repository->existsByEmail($email)) {
            $this->error('Un utilisateur avec cette adresse e-mail existe déjà.');
            return;
        }

        $repository->createAdministrator($email, password_hash($password, PASSWORD_DEFAULT));
        $this->success('Administrateur créé.');
    }

    private function prompt(string $message): string
    {
        fwrite(STDOUT, $message);
        return trim((string) fgets(STDIN));
    }

    private function promptHidden(string $message): string
    {
        fwrite(STDOUT, $message);
        $sttyMode = trim((string) shell_exec('stty -g'));
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty ' . escapeshellarg($sttyMode));
        fwrite(STDOUT, PHP_EOL);

        return $value;
    }
}
