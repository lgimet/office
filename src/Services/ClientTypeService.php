<?php

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Repositories\ClientTypeRepository;

class ClientTypeService
{
    public function __construct(private ClientTypeRepository $types, private ClientRepository $clients)
    {
    }
    public function all(): array
    {
        return $this->types->all();
    }
    public function activeOptions(): array
    {
        return array_map(
            static fn (array $type): array => ['id' => $type['id'], 'label' => $type['name']],
            $this->types->active()
        );
    }
    public function apiList(): array
    {
        return $this->types->activeApiList();
    }
    public function save(array $i): int
    {
        $id = isset($i['id']) ? (int)$i['id'] : null;
        $name = trim((string)($i['name'] ?? ''));
        $slug = trim((string)($i['slug'] ?? '')) ?: $this->slugify($name);
        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }if ($this->types->slugExists($slug, $id)) {
            throw new \InvalidArgumentException('Ce slug est déjà utilisé.');
        }return $this->types->save([$name,$slug,trim((string)($i['description'] ?? '')) ?: null,(int)($i['position'] ?? 0),!empty($i['is_active']) ? 1 : 0], $id);
    }
    public function toggle(int $id): void
    {
        $this->types->toggle($id);
    }
    public function delete(int $id): void
    {
        if ($this->clients->countByType($id) > 0) {
            throw new \DomainException('Ce type est associé à des clients ; désactivez-le plutôt que de le supprimer.');
        }$this->types->delete($id);
    }
    private function slugify(string $s): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
        return trim(strtolower((string)preg_replace('/[^a-z0-9]+/', '-', $s)), '-');
    }
}
