<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\ClientTypeService;
use App\Services\InternalApiKeyService;

class ApiClientTypes extends BaseController
{
    public function __construct(
        private ClientTypeService $service,
        private InternalApiKeyService $apiKey
    ) {
        parent::__construct();
    }

    #[Route(method: 'GET', path: 'api/v1/client-types', api: true)]
    public function index(): Response
    {
        if (!$this->apiKey->isValid()) {
            return (new Response())->setRawPayload([
                'success' => false,
                'error' => 'Clé API invalide.',
            ], 401);
        }

        return (new Response())->setRawPayload([
            'success' => true,
            'client_types' => $this->service->apiList(),
        ]);
    }

}
