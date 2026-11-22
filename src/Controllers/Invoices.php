<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use App\Services\DevsysClientService;
use App\Services\DevsysInvoiceReadService;
use App\Services\DevsysInvoiceDraftService;
use App\Services\InvoicePdfService;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiException;

class Invoices extends BaseController
{
    public function __construct(
        private InvoiceService $service,
        private InvoiceRepository $repository,
        private DevsysClientService $clients,
        private InvoicePdfService $pdf,
        private DevsysInvoiceReadService $invoiceRead,
        private DevsysInvoiceDraftService $draftWrites
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
        if (($_ENV['OFFICE_INVOICE_READ_API_ENABLED'] ?? 'false') === 'true') {
            try {
                $result = $this->invoiceRead->list($_GET);
                foreach ($result['rows'] as $index => $row) {
                    if (($row['status'] ?? null) !== 'draft' || !is_int($row['id'] ?? null)) continue;
                    try {
                        $result['rows'][$index] = [...$row, ...$this->draftWrites->actionAvailability($row['id'])];
                    } catch (\Throwable) {
                        // Unknown provenance must not expose edit, issue, or delete actions in the table.
                        $result['rows'][$index] = [...$row, 'api_mode' => true, 'can_edit' => false, 'can_issue' => false, 'can_delete' => false];
                    }
                }
                return (new Response())->setPayload($result);
            } catch (DevsysApiException $exception) {
                error_log(sprintf('[Devsys API] invoice list status=%s request_id=%s', $exception->getStatusCode() ?? 'none', $exception->getRequestId() ?? 'none'));
                $status = $exception->getStatusCode();
                if (in_array($status, [401, 403, 404], true)) {
                    $message = match ($status) {
                        401 => 'Votre session ne permet pas de consulter les factures. Reconnectez-vous.',
                        403 => 'L’accès aux factures a été refusé.',
                        default => 'La liste des factures est introuvable.',
                    };
                    return (new Response())->setError($status, $message);
                }
                return (new Response())->setError(503, 'Le service de factures est temporairement indisponible.');
            } catch (\InvalidArgumentException) {
                error_log('[Devsys API] invoice list status=invalid_response request_id=none');
                return (new Response())->setError(503, 'Le service de factures a retourné une réponse invalide.');
            }
        }

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
    public function actionAvailability(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $this->draftWrites->assertNoTenantSelectors($data);
            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) return (new Response())->setError(422, 'La facture demandée est invalide.');
            return (new Response())->setPayload($this->draftWrites->actionAvailability((int) $id));
        } catch (DevsysApiException $exception) {
            return $this->invoiceDraftApiError($exception);
        } catch (\UnexpectedValueException) {
            return (new Response())->setError(503, 'Le service de factures a retourné une réponse invalide. Aucune écriture SQL de secours n’a été tentée.');
        } catch (\LogicException $exception) {
            return (new Response())->setError(409, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function form(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $id = isset($data['id']) ? (int) $data['id'] : null;
            $form = $this->draftWrites->form($id);

            $response = (new Response())
                ->setId($id)
                ->setTitle($id === null ? 'Nouvelle facture' : 'Modifier le brouillon')
                ->setHtml($this->render('form.twig', [
                    ...$form,
                    'today' => date('Y-m-d'),
                ]));
            return $response->setPayload([
                'api_mode' => $form['api_mode'] ?? false,
                'api_writable' => $form['api_writable'] ?? false,
            ]);
        } catch (DevsysApiException $exception) {
            return $this->invoiceDraftApiError($exception);
        } catch (\UnexpectedValueException) {
            return (new Response())->setError(503, 'Le service de factures a retourné une réponse invalide. Aucune écriture SQL de secours n’a été tentée.');
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        } catch (\LogicException $exception) {
            return (new Response())->setError(409, $exception->getMessage());
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

    #[Route(method: 'GET', path: 'Invoices/{id:\\d+}/pdf')]
    #[AuthRequired]
    public function pdf(array $input): Response
    {
        try {
            $id = (int) ($input['id'] ?? 0);
            $view = $this->service->view($id);
            $invoice = $view['invoice'];
            $content = $this->pdf->render($invoice, $view['lines']);
            $number = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $invoice['invoice_number']) ?: 'facture';

            return (new Response())->setBinary($content, 'application/pdf', 'Facture-' . $number . '.pdf');
        } catch (\InvalidArgumentException | \LogicException | \RuntimeException $exception) {
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
            $result = $this->draftWrites->saveDraft($data, $id);

            return (new Response())
                ->setId($result['id'])
                ->setPayload($result)
                ->setToast(
                    $id === null
                        ? 'La facture a été enregistrée en brouillon.'
                        : 'Le brouillon a été mis à jour.'
                );
        } catch (DevsysApiException $exception) {
            return $this->invoiceDraftApiError($exception);
        } catch (\UnexpectedValueException) {
            return (new Response())->setError(503, 'Le service de factures a retourné une réponse invalide. Aucune écriture SQL de secours n’a été tentée.');
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        } catch (\LogicException $exception) {
            return (new Response())->setError(409, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function issue(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $id = isset($data['id']) ? (int) $data['id'] : null;
            $this->draftWrites->assertNoTenantSelectors($data);
            $number = $this->draftWrites->issueDraft($data, $id);

            return (new Response())
                ->setId($id)
                ->setToast("La facture {$number} a été émise.");
        } catch (DevsysApiException $exception) {
            return $this->invoiceDraftApiError($exception);
        } catch (\UnexpectedValueException) {
            return (new Response())->setError(503, 'Le service de factures a retourné une réponse invalide. Aucune émission SQL de secours n’a été tentée.');
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        } catch (\LogicException $exception) {
            return (new Response())->setError(409, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function delete(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $this->draftWrites->assertNoTenantSelectors($data);
            $this->draftWrites->deleteDraft((int) ($data['id'] ?? 0), $data);

            return (new Response())->setToast('Le brouillon a été supprimé.');
        } catch (DevsysApiException $exception) {
            return $this->invoiceDraftApiError($exception);
        } catch (\UnexpectedValueException) {
            return (new Response())->setError(503, 'Le service de factures a retourné une réponse invalide. Aucune suppression SQL de secours n’a été tentée.');
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        } catch (\LogicException $exception) {
            return (new Response())->setError(409, $exception->getMessage());
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

    private function invoiceDraftApiError(DevsysApiException $exception): Response
    {
        $status = $exception->getStatusCode();
        error_log(sprintf('[Devsys API] invoice draft status=%s code=%s request_id=%s', $status ?? 'network', $exception->getErrorCode() ?? 'none', $exception->getRequestId() ?? 'none'));
        $errorCode = $exception->getErrorCode();
        [$httpStatus, $message] = match (true) {
            $status === 401 => [401, 'Votre session ne permet pas d’accéder à cette facture. Reconnectez-vous.'],
            $status === 403 => [403, 'L’accès à cette facture a été refusé.'],
            $status === 404 => [404, 'La facture est introuvable ou inaccessible. Aucune écriture locale n’a été tentée.'],
            $status === 409 && $errorCode === 'IDEMPOTENCY_CONFLICT' => [409, 'Cette tentative a déjà été utilisée avec d’autres valeurs. Ouvrez un nouveau formulaire.'],
            $status === 409 && $errorCode === 'INVOICE_NOT_MODIFIABLE' => [409, 'Cette facture ne peut plus être modifiée. Rechargez-la.'],
            $status === 409 => [409, 'La facture a changé ou ne peut plus être modifiée. Rechargez-la.'],
            $status === 412 => [412, 'La facture a changé depuis son ouverture. Fermez et rouvrez le brouillon pour recharger sa version; vos valeurs saisies sont conservées dans ce formulaire.'],
            $status === 428 => [428, 'La version de la facture manque. Fermez et rouvrez le brouillon avant de réessayer.'],
            $status === 422 => [422, 'L’API a refusé les valeurs saisies. Vérifiez les champs et réessayez.'],
            default => [503, 'Le service de factures est indisponible. Vos valeurs restent dans le formulaire; aucun parcours SQL de secours n’a été utilisé.'],
        };
        return (new Response())->setError($httpStatus, $message);
    }
}
