<?php

namespace App\Api\Devsys\Dto;

final readonly class ClientListQuery
{
    private const SORT_MAPPING = [
        'client_name' => 'display_name',
        'client_type_name' => 'type',
        'email' => 'email',
        'city' => 'city',
        'status' => 'status',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    private const DIRECTIONS = ['asc', 'desc'];
    private const TYPES = ['company', 'association', 'individual_business', 'individual'];

    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
        public ?string $search = null,
        public ?string $clientType = null,
        public ?string $status = null,
        public string $sort = 'updated_at',
        public string $direction = 'desc'
    ) {
    }

    public static function fromDynamicTable(array $query): self
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['limit'] ?? 25)));
        $search = self::text($query['search'] ?? null);
        $clientType = self::type($query['type'] ?? null);
        $status = self::text($query['status'] ?? null);
        $sort = self::SORT_MAPPING[$query['sort'] ?? ''] ?? 'updated_at';
        $direction = strtolower((string) ($query['dir'] ?? 'desc'));

        return new self(
            $page,
            $perPage,
            $search,
            $clientType,
            $status,
            $sort,
            in_array($direction, self::DIRECTIONS, true) ? $direction : 'desc'
        );
    }

    public function toQueryParameters(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'search' => $this->search,
            'type' => $this->clientType,
            'status' => $this->status,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }

    public static function typeOptions(): array
    {
        return [
            ['id' => 'company', 'label' => 'Société'],
            ['id' => 'association', 'label' => 'Association'],
            ['id' => 'individual_business', 'label' => 'Entreprise individuelle'],
            ['id' => 'individual', 'label' => 'Particulier'],
        ];
    }

    private static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 190);
    }

    private static function type(mixed $value): ?string
    {
        $value = self::text($value);

        return in_array($value, self::TYPES, true) ? $value : null;
    }
}
