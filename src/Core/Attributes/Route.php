<?php
    namespace App\Core\Attributes;

    #[\Attribute(\Attribute::TARGET_METHOD)]
    class Route
    {
        public string $method;

        public function __construct(string $method = 'POST') {
            $this->method = $method;
        }
    }
