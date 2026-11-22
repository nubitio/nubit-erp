<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Service\CreditNoteLinePreparer;
use App\Billing\Service\CreditNoteReturnSelector;
use App\Settings\Service\TenantSettingsService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CreditNoteLinePreparerTest extends TestCase
{
    public function testRestockChoiceSurvivesLineRebuildAndTotalsAreRederived(): void
    {
        $related = new TaxDocument();
        $related->setDocumentType('01');
        $related->setSeries('F001');
        $related->setNumber('1');
        $related->setTotal('236.00');
        foreach ([1 => 'Audífonos', 2 => 'Cable'] as $number => $name) {
            $line = new TaxDocumentLine();
            $line->setLineNumber($number);
            $line->setProductName($name);
            $line->setQuantity('2.000000');
            $line->setUnitPrice('59.000000');
            $line->setTaxType('10');
            $related->addLine($line);
        }

        $note = new TaxDocument();
        $note->setRelatedTaxDocument($related);
        $note->setReasonCode('07');
        foreach ([[1, true], [2, false]] as [$number, $restock]) {
            $request = new TaxDocumentLine();
            $request->setLineNumber($number);
            $request->setQuantity('1');
            $request->setRestock($restock);
            $note->addLine($request);
        }

        $settings = $this->createStub(TenantSettingsService::class);
        $settings->method('getIgv')->willReturn(18.0);
        $repository = $this->createStub(TaxDocumentRepository::class);
        $repository->method('getReturnedQuantity')->willReturn(0.0);
        $repository->method('getSalesCurrencyForSaleId')->willReturn(['currency' => 'PEN', 'exchange_rate' => '1']);

        new CreditNoteLinePreparer($repository, $settings, new CreditNoteReturnSelector($repository))->prepare($note);

        $lines = array_values($note->getLines()->toArray());
        self::assertCount(2, $lines);
        self::assertTrue($lines[0]->isRestock());
        self::assertFalse($lines[1]->isRestock());
        self::assertSame('Cable', $lines[1]->getProductName(), 'lines are rebuilt from the affected document');
        self::assertSame('118.00', number_format((float) $note->getTotal(), 2, '.', ''));
    }
}
