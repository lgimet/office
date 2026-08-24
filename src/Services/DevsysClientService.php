<?php

declare(strict_types=1);

namespace App\Services;

use Devsys\Shared\Api\Devsys\Clients\ClientsApi;
use Devsys\Shared\Api\Devsys\Clients\Dto\ClientCategory;
use Devsys\Shared\Api\Devsys\Clients\Dto\ClientDetails;
use Devsys\Shared\Api\Devsys\Clients\Dto\ClientLegalType;

final class DevsysClientService
{
    public function __construct(private readonly ClientsApi $clientsApi)
    {
    }

    /** @return array{client: array<string, mixed>, etag: ?string} */
    public function form(?string $clientUuid): array
    {
        if ($clientUuid === null || $clientUuid === '') {
            return [
                'client' => [
                    'legal_type' => 'company',
                    'client_category_id' => null,
                    'status' => 'active',
                    'country_code' => 'FR',
                ],
                'etag' => null,
            ];
        }

        $client = $this->clientsApi->get($clientUuid);

        return [
            'client' => $this->toFormValues($client),
            'etag' => $client->etag,
        ];
    }

    public function save(array $values): ClientDetails
    {
        $clientUuid = $this->nullableString($values['id'] ?? null);
        $payload = $this->toApiPayload($values, $clientUuid === null);

        if ($clientUuid === null) {
            return $this->clientsApi->create($payload);
        }

        return $this->clientsApi->update(
            $clientUuid,
            $payload,
            $this->nullableString($values['etag'] ?? null),
        );
    }

    /** @return array<int, array{id: string, label: string}> */
    public function legalTypeOptions(): array
    {
        return array_map(
            static fn (ClientLegalType $type): array => [
                'id' => $type->slug,
                'label' => $type->name,
            ],
            $this->clientsApi->legalTypes(),
        );
    }

    /** @return array<int, array{id: int, label: string, slug: string}> */
    public function clientCategoryOptions(): array
    {
        return array_map(
            static fn (ClientCategory $category): array => [
                'id' => $category->id,
                'label' => $category->name,
                'slug' => $category->slug,
            ],
            $this->clientsApi->clientCategories(),
        );
    }

    /** @return array<int, array{id: string, label: string}> */
    public function statusOptions(): array
    {
        return [
            ['id' => 'active', 'label' => 'Actif'],
            ['id' => 'inactive', 'label' => 'Inactif'],
            ['id' => 'prospect', 'label' => 'Prospect'],
            ['id' => 'archived', 'label' => 'Archivé'],
        ];
    }

    /** @return array<string, mixed> */
    private function toFormValues(ClientDetails $client): array
    {
        return [
            'id' => $client->id,
            'legal_type' => $client->legalType->slug,
            'client_category_id' => $client->clientCategory?->id,
            'company_name' => $client->companyName,
            'legal_name' => $client->legalName,
            'contact_first_name' => $this->arrayValue($client->contact, 'first_name'),
            'contact_last_name' => $this->arrayValue($client->contact, 'last_name'),
            'email' => $this->arrayValue($client->contact, 'email'),
            'phone' => $this->arrayValue($client->contact, 'phone'),
            'address_line1' => $this->arrayValue($client->address, 'line1'),
            'address_line2' => $this->arrayValue($client->address, 'line2'),
            'postal_code' => $this->arrayValue($client->address, 'postal_code'),
            'city' => $this->arrayValue($client->address, 'city'),
            'country_code' => $this->arrayValue($client->address, 'country_code') ?? 'FR',
            'siret' => $this->arrayValue($client->business, 'siret'),
            'siren' => $this->arrayValue($client->business, 'siren'),
            'vat_number' => $this->arrayValue($client->business, 'vat_number'),
            'website' => $client->website,
            'notes' => $client->notes,
            'status' => $client->status,
        ];
    }

    /** @return array<string, mixed> */
    private function toApiPayload(array $values, bool $isCreation): array
    {
        $payload = [
            'legal_type' => $this->nullableString($values['legal_type'] ?? null),
            'company_name' => $this->nullableString($values['company_name'] ?? null),
            'legal_name' => $this->nullableString($values['legal_name'] ?? null),
            'first_name' => $this->nullableString($values['contact_first_name'] ?? null),
            'last_name' => $this->nullableString($values['contact_last_name'] ?? null),
            'email' => $this->nullableString($values['email'] ?? null),
            'phone' => $this->nullableString($values['phone'] ?? null),
            'address' => [
                'line1' => $this->nullableString($values['address_line1'] ?? null),
                'line2' => $this->nullableString($values['address_line2'] ?? null),
                'postal_code' => $this->nullableString($values['postal_code'] ?? null),
                'city' => $this->nullableString($values['city'] ?? null),
                'country_code' => $this->countryCode($values['country_code'] ?? null),
            ],
            'siret' => $this->nullableString($values['siret'] ?? null),
            'siren' => $this->nullableString($values['siren'] ?? null),
            'vat_number' => $this->nullableString($values['vat_number'] ?? null),
            'website' => $this->nullableString($values['website'] ?? null),
            'notes' => $this->nullableString($values['notes'] ?? null),
            'status' => $this->nullableString($values['status'] ?? null),
        ];

        $categoryId = $this->nullablePositiveInteger($values['client_category_id'] ?? null);
        if ($isCreation || $categoryId !== null) {
            $payload['client_category_id'] = $categoryId;
        }

        return $payload;
    }

    private function countryCode(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value === 'France' ? 'FR' : $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        if (!ctype_digit($value) || (int) $value < 1) {
            throw new \InvalidArgumentException('La catégorie client sélectionnée est invalide.');
        }

        return (int) $value;
    }

    private function arrayValue(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
