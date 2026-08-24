<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\CompanySettingsService;

class Company extends BaseController
{
    public function __construct(private CompanySettingsService $service)
    {
        parent::__construct();
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function form(): Response
    {
        return (new Response())
            ->setTitle('Société')
            ->setHtml($this->render('form.twig', $this->service->form()));
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function save(array $input): Response
    {
        try {
            $this->service->save($this->payload($input));

            return (new Response())->setToast('Les informations de la société ont été enregistrées.');
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        }
    }

    private function payload(array $input): array
    {
        $data = $input['vars'] ?? [];

        return is_array($data['vars'] ?? null) ? $data['vars'] : $data;
    }
}
