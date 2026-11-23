<?php

namespace App\Api\Devsys\Dto;

final readonly class ClientListItem
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $type,
        public ?string $email,
        public ?string $phone,
        public ?string $city,
        public string $status
    ) {
    }

    public static function fromApiResponse(array $item): self
    {
        if (!is_string($item['id'] ?? null) || !is_string($item['display_name'] ?? null) || !is_string($item['type'] ?? null) || !is_string($item['status'] ?? null)) {
            throw new \UnexpectedValueException('La structure d’un client API est invalide.');
        }

        return new self(
            $item['id'],
            $item['display_name'],
            $item['type'],
            self::nullableString($item['email'] ?? null),
            self::nullableString($item['phone'] ?? null),
            self::nullableString($item['city'] ?? null),
            $item['status']
        );
    }

    public function typeLabel(): string
    {
        return [
            'company' => 'Société',
            'association' => 'Association',
            'individual_business' => 'Entreprise individuelle',
            'individual' => 'Particulier',
        ][$this->type] ?? $this->type;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
