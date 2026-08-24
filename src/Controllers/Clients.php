<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\DevsysClientService;
use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Clients\Dto\ClientListItem;
use Devsys\Shared\Api\Devsys\Clients\Dto\ClientListQuery;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiException;
use Devsys\Shared\Api\Devsys\Exception\DevsysApiValidationException;

class Clients extends BaseController
{
    public function __construct(
        private ClientsApi $clientsApi,
        private DevsysClientService $clientService,
    ) {
        parent::__construct();
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function list(): Response
    {
        return (new Response())
            ->setTitle('Clients')
            ->setHtml($this->render('list.twig'));
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function data(): Response
    {
        try {
            $result = $this->clientsApi->list($this->listQuery($_GET));

            $rows = array_map(fn (ClientListItem $client): array => [
                'id' => $client->id,
                'client_name' => $client->displayName,
                'client_category_name' => $client->clientCategory?->name ?? '—',
                'legal_type_name' => $client->legalType->name,
                'contact_name' => null,
                'email' => $client->email,
                'phone' => $client->phone,
                'city' => $client->city,
                'status' => $client->status,
            ], $result->items);

            return (new Response())->setPayload([
                'rows' => $rows,
                'pages' => max(1, $result->pagination->totalPages),
                'page' => $result->pagination->page,
                'per_page' => $result->pagination->perPage,
                'total' => $result->pagination->total,
            ]);
        } catch (DevsysApiException $exception) {
            error_log(sprintf(
                '[Devsys API] GET %s failed: status=%s request_id=%s',
                $exception->getRequestPath() ?? '/clients',
                $exception->getStatusCode() ?? 'none',
                $exception->getRequestId() ?? 'none'
            ));

            return (new Response())->setError(
                503,
                'La liste des clients est temporairement indisponible.'
            );
        } catch (\InvalidArgumentException) {
            return (new Response())->setError(
                422,
                'Les filtres clients demandés sont invalides.'
            );
        }
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function legalTypes(): Response
    {
        try {
            return (new Response())->setPayload($this->clientService->legalTypeOptions());
        } catch (DevsysApiException $exception) {
            return $this->apiError($exception);
        }
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function categories(): Response
    {
        try {
            return (new Response())->setPayload($this->clientService->clientCategoryOptions());
        } catch (DevsysApiException $exception) {
            return $this->apiError($exception);
        }
    }

    #[Route(method: 'GET')]
    #[AuthRequired]
    public function statuses(): Response
    {
        return (new Response())->setPayload($this->clientService->statusOptions());
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function form(array $input): Response
    {
        $data = $this->payload($input);
        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;

        try {
            return (new Response())
                ->setTitle($id ? 'Modifier le client' : 'Ajouter un client')
                ->setHtml($this->render('form.twig', $this->clientService->form($id)))
                ->setId($id);
        } catch (DevsysApiException $exception) {
            return $this->apiError($exception);
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function save(array $input): Response
    {
        try {
            $client = $this->clientService->save($this->payload($input));

            return (new Response())
                ->setId($client->id)
                ->setPayload([
                    'etag' => $client->etag,
                ])
                ->setToast('Client enregistré.');
        } catch (DevsysApiValidationException $exception) {
            return (new Response())
                ->setPayload(['errors' => $exception->getErrors()])
                ->setError(422, $this->validationMessage($exception));
        } catch (DevsysApiException $exception) {
            return $this->apiError($exception);
        } catch (\InvalidArgumentException $exception) {
            return (new Response())->setError(422, $exception->getMessage());
        }
    }

    #[Route(method: 'POST')]
    #[AuthRequired]
    public function toggle(array $input): Response
    {
        return (new Response())->setError(405, 'Cette action n’est plus disponible.');
    }

    private function payload(array $input): array
    {
        $data = $input['vars'] ?? [];

        return is_array($data['vars'] ?? null) ? $data['vars'] : $data;
    }

    private function apiError(DevsysApiException $exception): Response
    {
        $statusCode = $exception->getStatusCode();

        error_log(sprintf(
            '[Devsys API] path=%s status=%s request_id=%s',
            $exception->getRequestPath() ?? 'unknown',
            $statusCode ?? 'none',
            $exception->getRequestId() ?? 'none',
        ));

        if ($statusCode === 412) {
            return (new Response())->setError(
                409,
                'Ce client a été modifié entre-temps. Actualisez la fiche avant de recommencer.',
            );
        }

        if ($statusCode === 409) {
            return (new Response())->setError(
                409,
                'Un autre client possède déjà ce numéro SIRET.',
            );
        }

        if (in_array($statusCode, [401, 403], true)) {
            return (new Response())->setError(
                502,
                'L’authentification auprès du service clients a échoué.',
            );
        }

        return (new Response())->setError(
            $statusCode === 404 ? 404 : 503,
            $statusCode === 404
                ? 'Le client demandé est introuvable.'
                : 'Le service clients est temporairement indisponible.',
        );
    }

    private function validationMessage(DevsysApiValidationException $exception): string
    {
        $messages = [
            'legal_type' => 'Le type juridique est invalide.',
            'client_category_id' => 'La catégorie client sélectionnée est invalide.',
            'status' => 'Le statut du client est invalide.',
            'name' => 'La raison sociale ou le nom du contact est obligatoire.',
            'email' => 'L’adresse e-mail est invalide.',
            'country_code' => 'Le code pays doit comporter deux lettres, par exemple FR.',
            'siret' => 'Le SIRET doit comporter 14 chiffres.',
            'siren' => 'Le SIREN doit comporter 9 chiffres.',
            'website' => 'L’adresse du site internet est invalide.',
            'notes' => 'Les notes sont trop longues.',
        ];

        foreach (array_keys($exception->getErrors()) as $field) {
            if (isset($messages[$field])) {
                return $messages[$field];
            }
        }

        return 'Les informations du client sont invalides.';
    }

    private function listQuery(array $query): ClientListQuery
    {
        $sortMapping = [
            'client_name' => 'display_name',
            'client_category_name' => 'client_category',
            'legal_type_name' => 'legal_type',
            'email' => 'email',
            'city' => 'city',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
        $statuses = ['active', 'inactive', 'prospect', 'archived'];
        $legalType = $this->optionalQueryValue($query['legal_type'] ?? null);
        $clientCategory = $this->optionalQueryValue($query['client_category'] ?? null);
        $status = $this->optionalQueryValue($query['status'] ?? null);
        $direction = strtolower((string) ($query['dir'] ?? 'desc'));

        return new ClientListQuery(
            page: max(1, (int) ($query['page'] ?? 1)),
            perPage: max(1, min(100, (int) ($query['limit'] ?? 25))),
            search: $this->optionalQueryValue($query['search'] ?? null),
            legalType: $legalType,
            clientCategory: $clientCategory,
            status: in_array($status, $statuses, true) ? $status : null,
            sort: $sortMapping[$query['sort'] ?? ''] ?? 'updated_at',
            direction: in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
        );
    }

    private function optionalQueryValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

}
