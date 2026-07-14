# Office

Socle du SaaS de gestion Office, basé sur Structure-Saas et utilisant PHP, Twig, FastRoute, MySQL et Sass.

## Prérequis

- PHP 8.1 ou supérieur avec les extensions `pdo_mysql` et `mbstring` ;
- Composer ;
- Node.js et npm ;
- MySQL 8 ou compatible.

## Installation

Installez les dépendances PHP et JavaScript :

```bash
composer install
npm install
```

Copiez `.env.example` vers `.env`, puis renseignez les valeurs locales, notamment `DB_USER`, `DB_PASSWORD` et `JWT_SECRET`. Aucun fichier `.env` ne doit être versionné.

Créez la base vide puis appliquez le schéma :

```bash
mysql -u <utilisateur> -p -e "CREATE DATABASE office CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <utilisateur> -p office < database/001_create_users.sql
```

Créez le premier administrateur. La commande demande l’adresse e-mail et le mot de passe, qui est hashé avec `password_hash()` :

```bash
php bin/console user:create-admin
```

## Assets Sass

```bash
npm run sass
npm run watch
```

## Démarrage local

Le point d’entrée HTTP est `public/index.php`. Depuis la racine du projet :

```bash
php -S localhost:8000 -t public
```
