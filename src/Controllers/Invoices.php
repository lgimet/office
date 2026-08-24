<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;

class Invoices extends BaseController
{
    public function __construct(
        private InvoiceService $service,
        private InvoiceRepository $repository
    ) {
        parent::__construct();
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function list(): Response
    {
        return (new Response())
            ->setTitle('Factures')
            ->setHtml($this->render('list.twig'));
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function data(): Response
    {
        return (new Response())->setPayload($this->service->list($_GET));
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function clientOptions(): Response
    {
        return (new Response())->setPayload(
            $this->repository->clientOptions(trim((string) ($_GET['q'] ?? '')))
        );
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function form(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $id = isset($data['id']) ? (int) $data['id'] : null;
            $form = $this->service->form($id);

            return (new Response())
                ->setId($id)
                ->setTitle($id === null ? 'Nouvelle facture' : 'Modifier le brouillon')
                ->setHtml($this->render('form.twig', [
                    ...$form,
                    'today' => date('Y-m-d'),
                ]));
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function save(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $id = isset($data['id']) ? (int) $data['id'] : null;
            $invoiceId = $this->service->saveDraft($data, $id);

            return (new Response())
                ->setId($invoiceId)
                ->setToast(
                    $id === null
                        ? 'La facture a été enregistrée en brouillon.'
                        : 'Le brouillon a été mis à jour.'
                );
        } catch (\InvalidArgumentException | \LogicException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function delete(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $this->service->deleteDraft((int) ($data['id'] ?? 0));

            return (new Response())->setToast('Le brouillon a été supprimé.');
        } catch (\InvalidArgumentException | \LogicException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        }
    }

    private function payload(array $input): array
    {
        $data = $input['vars'] ?? [];

        return is_array($data['vars'] ?? null) ? $data['vars'] : $data;
    }
}
