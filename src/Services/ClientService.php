<?php

namespace App\Services;

use App\Repositories\ClientRepository;

class ClientService
{
    public function __construct(private ClientRepository $clients)
    {
    }
    public function list(array $query): array
    {
        $limit = max(1, min(100, (int)($query['limit'] ?? 25)));
        $page = max(1, (int)($query['page'] ?? 1));
        $result = $this->clients->paginate(trim((string)($query['search'] ?? '')), ($query['client_type_id'] ?? '') !== '' ? (int)$query['client_type_id'] : null, $page, $limit);
        return ['rows' => $result['rows'],'pages' => (int)max(1, ceil($result['total'] / $limit))];
    }
    public function form(?int $id = null): array
    {
        return ['client' => $id ? $this->clients->find($id) : null];
    }
    public function save(array $input, ?int $id = null): int
    {
        $data = $this->normalize($input);
        if (!$data['client_type_id'] || $data['company_name'] === '') {
            throw new \InvalidArgumentException('Le type de client et la raison sociale sont obligatoires.');
        } return $id ? ($this->clients->update($id, $data) ?: $id) : $this->clients->create($data);
    }
    public function toggle(int $id): void
    {
        $this->clients->toggle($id);
    }
    private function normalize(array $i): array
    {
        $keys = ['display_name','contact_first_name','contact_last_name','email','phone','address_line1','address_line2','postal_code','city','siret','vat_number','notes'];
        $d = ['client_type_id' => (int)($i['client_type_id'] ?? 0),'company_name' => trim((string)($i['company_name'] ?? '')),'country' => trim((string)($i['country'] ?? 'France')) ?: 'France','is_active' => !empty($i['is_active']) ? 1 : 0];
        foreach ($keys as $k) {
            $v = trim((string)($i[$k] ?? ''));
            $d[$k] = $v === '' ? null : $v;
        }return $d;
    }
}
