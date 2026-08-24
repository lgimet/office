<?php

namespace App\Core\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Route
{
    public string $method;
    public ?string $path;
    public bool $api;

    public function __construct(string $method = 'POST', ?string $path = null, bool $api = false)
    {
        $this->method = $method;
        $this->path = $path;
        $this->api = $api;
    }
}
