<?php

namespace App\Services\Oidc;

interface OidcHttpClientInterface
{
    /** @return array{status:int, body:array<string,mixed>, headers:array<string,string>} */
    public function getJson(string $url, array $headers = []): array;

    /** @return array{status:int, body:array<string,mixed>, headers:array<string,string>} */
    public function postForm(string $url, array $fields, ?array $basicAuth = null): array;

    /** @return array{status:int, body:array<string,mixed>, headers:array<string,string>} */
    public function getBearer(string $url, string $token): array;
}
