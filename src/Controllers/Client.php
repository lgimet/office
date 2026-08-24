<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\Client as ServicesClient;

class Client extends BaseController
{
    public function __construct(private ServicesClient $service)
    {
        parent::__construct();
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function form(): Response
    {
        $data = $this->service->getFormData();
        $html = $this->render('form.twig', $data);

        return (new Response())
                ->setTitle('Client')
                ->setHtml($html);
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function options($input = []): Response
    {
        $type = $_GET['type'] ?? 'countries';

        return (new Response())
            ->setPayload($this->service->getOptions($type));
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function update($input): Response
    {
        $data = $input['vars'] ?? [];

        $this->service->updateClient($data);

        return (new Response())
            ->setToast("Client mis a jour");
    }
}
