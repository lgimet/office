<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\AuthService;
use App\Services\DashboardService;

class DashboardController extends BaseController
{
    public $user;
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        parent::__construct();
        $this->authService = $authService ?? $this->service(AuthService::class);
    }

    public function index()
    {
        $this->user = $this->authService->verify(false);
        $ds = $this->service(DashboardService::class);
        $data = $ds->getFormData($this->user);

        echo $this->render('dashboard.twig', $data);

        //require __DIR__.'/../../views/dashboard/index.php';
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function demoOptions($input = [])
    {
        $type = $_GET['type'] ?? 'countries';

        $options = match ($type) {
            'activities' => [
                ['id' => 'crm', 'label' => 'CRM'],
                ['id' => 'billing', 'label' => 'Facturation'],
                ['id' => 'analytics', 'label' => 'Analytics'],
                ['id' => 'support', 'label' => 'Support client'],
            ],
            default => [
                ['id' => 'fr', 'label' => 'France'],
                ['id' => 'be', 'label' => 'Belgique'],
                ['id' => 'ch', 'label' => 'Suisse'],
                ['id' => 'ca', 'label' => 'Canada'],
            ],
        };

        return (new Response())->setPayload($options);
    }

}
