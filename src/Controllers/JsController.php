<?php

namespace App\Controllers;

use App\Core\Exceptions\HttpException;

class JsController {

    public function get($input) {
        // sécurité
        $file = str_replace(['..', '\\'], '', $input['file']);    
        $path = __DIR__."/../../public/".$file;
        if (!file_exists($path)) {
            throw new HttpException(404, 'Fichier introuvable');
        }
         $mtime = filemtime($path);

        header('Content-Type: application/javascript');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo "/*****************************".PHP_EOL;
        echo " * DEBUG -- ".time().PHP_EOL;
        echo " *****************************/".PHP_EOL;
        echo file_get_contents($path);
    }
}
?>
