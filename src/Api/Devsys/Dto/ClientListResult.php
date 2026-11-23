<?php

namespace App\Api\Devsys\Dto;

final readonly class ClientListResult
{
    /** @param ClientListItem[] $items */
    public function __construct(public array $items, public Pagination $pagination)
    {
    }
}
