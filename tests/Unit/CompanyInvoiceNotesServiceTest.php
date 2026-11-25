<?php

namespace App\Tests\Unit;

use App\Services\CompanyInvoiceNotesService;
use PHPUnit\Framework\TestCase;

final class CompanyInvoiceNotesServiceTest extends TestCase
{
    public function testMapsOnlyTheThreeTypedNotesIntoCompanyFormFields(): void
    {
        self::assertSame([
            'invoice_note_pmd' => 'PMD fictif',
            'invoice_note_pmt' => 'PMT fictif',
            'invoice_note_aab' => 'AAB fictif',
        ], CompanyInvoiceNotesService::mapToOffice(['PMD' => 'PMD fictif', 'PMT' => 'PMT fictif', 'AAB' => 'AAB fictif']));
    }
}
