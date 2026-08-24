<?php

namespace App\Services;

use App\Helpers\Csrf;

class DashboardService
{
    public function getFormData(): array
    {
        $user = (array)$_SESSION['user'];

        unset($user['iat']);
        unset($user['exp']);
        $data = [
            'user' => $user,
            'csrf' => Csrf::generate(),
            'menuItems' => [
                ['label' => 'Clients', 'icon' => 'bi-people', 'open' => true, 'items' => [
                    ['label' => 'Liste des clients', 'icon' => 'bi-buildings', 'action' => 'Clients.list'],
                ]],
                ['label' => 'Paramètres', 'icon' => 'bi-gear', 'items' => [
                    ['label' => 'Société', 'icon' => 'bi-building', 'action' => 'Company.form'],
                    ['label' => 'Types de clients', 'icon' => 'bi-tags', 'action' => 'ClientTypes.list'],
                ]],
                ['label' => 'Facturation', 'icon' => 'bi-receipt', 'open' => false, 'items' => [
                    ['label' => 'Factures', 'icon' => 'bi-file-earmark-text', 'action' => 'Invoices.list'],
                ]],
            ],
        ];
        return $data;
    }

}
