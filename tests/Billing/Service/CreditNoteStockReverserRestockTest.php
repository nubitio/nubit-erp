<?php

declare(strict_types=1);

namespace App\Tests\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Sale\Void\StockVoidReverserInterface;
use App\Billing\Service\CreditNoteStockReverser;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleRecord;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
final class CreditNoteStockReverserRestockTest extends TestCase
{
    /**
     * InventoryService is final and left uninitialised here: reaching it would throw,
     * so a passing test proves no movement was attempted for lines kept out of stock.
     */
    public function testLinesFlaggedWithoutRestockNeverReachTheInventory(): void
    {
        $sale = $this->createStub(SaleRecord::class);
        $sale->method('getWarehouse')->willReturn($this->createStub(Warehouse::class));

        $note = new TaxDocument();
        $note->setReasonCode('07');
        $note->setSaleRecord($sale);
        $line = new TaxDocumentLine();
        $line->setLineNumber(1);
        $line->setProductId('product-1');
        $line->setQuantity('1.000000');
        $line->setRestock(false);
        $note->addLine($line);

        /** @var InventoryService $inventory */
        $inventory = new ReflectionClass(InventoryService::class)->newInstanceWithoutConstructor();
        $reverser = new CreditNoteStockReverser(
            $inventory,
            $this->createStub(StockVoidReverserInterface::class),
            new NullLogger(),
        );

        $reverser->reverse($note);

        $this->addToAssertionCount(1);
    }
}
