<?php

namespace App\Api\Devsys\Dto;

final readonly class Pagination
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public int $totalPages
    ) {
    }
}
