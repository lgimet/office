<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\{CompanyInvoiceNotesService, CompanySettingsService};

class Company extends BaseController
{
    public function __construct(private CompanySettingsService $service, private CompanyInvoiceNotesService $invoiceNotes)
    {
        parent::__construct();
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function form(): Response
    {
        $data = $this->service->form();
        $data['company'] = array_merge($data['company'], $this->invoiceNotes->read());
        return (new Response())
            ->setTitle('Société')
            ->setHtml($this->render('form.twig', $data));
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function save(array $input): Response
    {
        try {
            $payload = $this->payload($input);
            $this->service->validate($payload);
            $this->invoiceNotes->replace($payload);
            $this->service->save($payload);

            return (new Response())->setToast('Les informations de la société ont été enregistrées.');
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        } catch (\Throwable) {
            return (new Response())->setError(503, 'La configuration n’a pas pu être enregistrée complètement. Vérifiez les valeurs et réessayez.');
        }
    }

    private function payload(array $input): array
    {
        $data = $input['vars'] ?? [];

        return is_array($data['vars'] ?? null) ? $data['vars'] : $data;
    }
}
