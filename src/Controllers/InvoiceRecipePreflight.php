<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Attributes\AuthRequired;
use App\Core\Attributes\Route;
use App\Core\BaseController;
use App\Helpers\Response;
use App\Services\OfficeInvoiceRecipePreflightService;

/** Temporary, CSRF-protected operator action; performs an API GET only. */
final class InvoiceRecipePreflight extends BaseController
{
    public function __construct(private readonly OfficeInvoiceRecipePreflightService $preflight)
    {
        parent::__construct();
    }

    #[Route(method: 'POST', path: 'Invoices/recipePreflight')]
    #[AuthRequired]
    public function run(): Response
    {
        if (!$this->preflight->isEnabledForCurrentOperator()) {
            return (new Response())->setRawPayload(['result' => 'UNAVAILABLE'], 404);
        }

        return (new Response())->setRawPayload(['result' => $this->preflight->check()]);
    }
}
