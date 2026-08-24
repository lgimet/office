<?php

namespace App\Controllers;

use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Exceptions\ApiValidationException;
use App\Helpers\Response;
use App\Services\ApiClientService;
use App\Services\InternalApiKeyService;

class ApiClients extends BaseController
{
    public function __construct(
        private ApiClientService $clients,
        private InternalApiKeyService $apiKey
    ) {
        parent::__construct();
    }

    #[Route(method: 'POST', path: 'api/v1/clients', api: true)]
    public function create(array $input): Response
    {
        if (!$this->apiKey->isValid()) {
            return (new Response())->setRawPayload([
                'success' => false,
                'message' => 'Clé API invalide.',
            ], 401);
        }

        try {
            $data = $input['vars'] ?? [];

            return (new Response())->setRawPayload([
                'success' => true,
                'client' => $this->clients->create($data),
            ], 201);
        } catch (ApiValidationException $exception) {
            return (new Response())->setRawPayload([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setRawPayload([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => [],
            ], 422);
        }
    }
}
