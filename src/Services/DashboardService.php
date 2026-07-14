<?php

namespace App\Services;

use App\Helpers\Csrf;

class DashboardService {

     public function getFormData() : array {
        $user = (array)$_SESSION['user'];
        
        unset($user['iat']);
        unset($user['exp']);
        $data = [
            'user' => $user,
            'csrf' => Csrf::generate()
        ];
        return $data;
     }

}