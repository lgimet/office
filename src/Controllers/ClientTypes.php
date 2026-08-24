<?php

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\ClientTypeService;

class ClientTypes extends BaseController
{
    public function __construct(private ClientTypeService $service)
    {
        parent::__construct();
    }
    #[Route(method: 'POST')] #[AuthRequired]
    public function list(): Response
    {
        return (new Response())->setTitle('Types de clients')->setHtml($this->render('list.twig'));
    }
    #[Route(method: 'GET')] #[AuthRequired]
    public function options(): Response
    {
        return (new Response())->setPayload($this->service->activeOptions());
    }
    #[Route(method: 'GET')] #[AuthRequired]
    public function data(): Response
    {
        $rows = $this->service->all();
        return (new Response())->setPayload(['rows' => $rows,'pages' => 1]);
    }
    #[Route(method: 'POST')] #[AuthRequired]
    public function save(array $input): Response
    {
        try {
            $id = $this->service->save($this->payload($input));
            return (new Response())->setId($id)->setToast('Type de client enregistré.');
        } catch (\InvalidArgumentException $e) {
            return (new Response())->setError(422, $e->getMessage());
        }
    }
    #[Route(method: 'POST')] #[AuthRequired]
    public function toggle(array $input): Response
    {
        $data = $this->payload($input);
        $this->service->toggle((int)($data['id'] ?? 0));
        return (new Response())->setToast('Statut du type mis à jour.');
    }
    #[Route(method: 'POST')] #[AuthRequired]
    public function delete(array $input): Response
    {
        try {
            $data = $this->payload($input);
            $this->service->delete((int)($data['id'] ?? 0));
            return (new Response())->setToast('Type de client supprimé.');
        } catch (\DomainException $e) {
            return (new Response())->setError(422, $e->getMessage());
        }
    }
    private function payload(array $input): array
    {
        $data = $input['vars'] ?? [];
        return is_array($data['vars'] ?? null) ? $data['vars'] : $data;
    }
}
