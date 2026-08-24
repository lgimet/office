<?php

namespace App\Services;

use App\Exceptions\ApiValidationException;
use App\Repositories\ClientRepository;
use App\Repositories\ClientTypeRepository;

class ApiClientService
{
    public function __construct(
        private ClientService $clients,
        private ClientRepository $clientRepository,
        private ClientTypeRepository $clientTypes
    ) {
    }

    public function create(array $input): array
    {
        $slug = strtolower(trim((string) ($input['client_type_slug'] ?? '')));

        if ($slug === '') {
            throw new ApiValidationException(
                'Le type de client demandé n’existe pas.',
                ['client_type_slug' => ['Le type de client demandé n’existe pas.']]
            );
        }

        $clientType = $this->clientTypes->findActiveBySlug($slug);

        if ($clientType === null) {
            throw new ApiValidationException(
                'Le type de client demandé n’existe pas.',
                ['client_type_slug' => ['Le type de client demandé n’existe pas.']]
            );
        }

        $companyName = trim((string) ($input['company_name'] ?? ''));

        if ($companyName === '') {
            throw new ApiValidationException(
                'La raison sociale est obligatoire.',
                ['company_name' => ['La raison sociale est obligatoire.']]
            );
        }

        $email = trim((string) ($input['email'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiValidationException(
                'L’adresse e-mail est invalide.',
                ['email' => ['L’adresse e-mail est invalide.']]
            );
        }

        $id = $this->clients->save([
            'client_type_id' => (int) $clientType['id'],
            'company_name' => $companyName,
            'display_name' => $this->nullable($input, 'display_name'),
            'contact_first_name' => $this->nullable($input, 'contact_first_name'),
            'contact_last_name' => $this->nullable($input, 'contact_last_name'),
            'email' => $email,
            'phone' => $this->nullable($input, 'phone'),
            'address_line1' => $this->nullable($input, 'address_line1'),
            'address_line2' => $this->nullable($input, 'address_line2'),
            'postal_code' => $this->nullable($input, 'postal_code'),
            'city' => $this->nullable($input, 'city'),
            'country' => $this->nullable($input, 'country') ?? 'France',
            'siret' => $this->nullable($input, 'siret'),
            'vat_number' => $this->nullable($input, 'vat_number'),
            'notes' => $this->nullable($input, 'notes'),
            'is_active' => 1,
        ]);

        $client = $this->clientRepository->find($id);

        if ($client === null) {
            throw new \LogicException('Le client créé est introuvable.');
        }

        return [
            'id' => (int) $client['id'],
            'client_type' => [
                'name' => $clientType['name'],
                'slug' => $clientType['slug'],
            ],
            'company_name' => $client['company_name'],
            'display_name' => $client['display_name'],
            'email' => $client['email'],
            'city' => $client['city'],
        ];
    }

    private function nullable(array $input, string $field): ?string
    {
        $value = trim((string) ($input[$field] ?? ''));

        return $value === '' ? null : $value;
    }
}
