<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use App\Services\DevsysClientService;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiException;

class Invoices extends BaseController
{
    public function __construct(
        private InvoiceService $service,
        private InvoiceRepository $repository,
        private DevsysClientService $clients
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
        try {
            return (new Response())->setPayload(
                $this->clients->searchInvoiceClientOptions(trim((string) ($_GET['q'] ?? '')))
            );
        } catch (DevsysApiException $exception) {
            return $this->clientApiError($exception, 'La recherche des clients est temporairement indisponible.');
        }
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function clientDetails(): Response
    {
        $uuid = trim((string) ($_GET['uuid'] ?? ''));
        try {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
                return (new Response())->setError(422, 'Le client sélectionné est invalide.');
            }
            return (new Response())->setPayload([$this->clients->invoiceClient($uuid)]);
        } catch (DevsysApiException $exception) {
            return $this->clientApiError($exception, 'Le service clients est temporairement indisponible.');
        }
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
    public function view(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $id = (int) ($data['id'] ?? 0);
            $view = $this->service->view($id);
            $number = $view['invoice']['invoice_number'] ?? ('#' . $id);

            return (new Response())
                ->setId($id)
                ->setTitle('Facture ' . $number)
                ->setHtml($this->render('view.twig', $view));
        } catch (\InvalidArgumentException | \LogicException $exception) {
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
        } catch (DevsysApiException $exception) {
            return $this->clientApiError($exception, 'Le service clients est temporairement indisponible.');
        } catch (\InvalidArgumentException | \LogicException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function issue(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $id = isset($data['id']) ? (int) $data['id'] : null;
            $number = $this->service->issueDraft($data, $id);

            return (new Response())
                ->setId($id)
                ->setToast("La facture {$number} a été émise.");
        } catch (DevsysApiException $exception) {
            return $this->clientApiError($exception, 'Le service clients est temporairement indisponible.');
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

    private function clientApiError(DevsysApiException $exception, string $message): Response
    {
        error_log(sprintf('[Devsys API] invoice client path=%s status=%s request_id=%s', $exception->getRequestPath() ?? 'unknown', $exception->getStatusCode() ?? 'none', $exception->getRequestId() ?? 'none'));
        return (new Response())->setError($exception->getStatusCode() === 404 ? 404 : 503, $message);
    }
}
