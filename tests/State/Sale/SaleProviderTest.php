<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Sales\State\SaleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaleProvider::class)]
class SaleProviderTest extends TestCase
{
    /** @var ProviderInterface<object>&MockObject */
    private ProviderInterface&MockObject $collectionProvider;

    /** @var ProviderInterface<object>&MockObject */
    private ProviderInterface&MockObject $itemProvider;

    private SaleProvider $saleProvider;

    protected function setUp(): void
    {
        $this->collectionProvider = $this->createMock(ProviderInterface::class);
        $this->itemProvider = $this->createMock(ProviderInterface::class);

        $this->saleProvider = new SaleProvider(
            $this->collectionProvider,
            $this->itemProvider,
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Capture the context passed to the collection provider and return it.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function captureCollectionContext(Operation $operation, array $context = []): array
    {
        $capturedContext = [];
        $this->collectionProvider->method('provide')
            ->willReturnCallback(function (Operation $op, array $uriVars, array $ctx) use (&$capturedContext): array {
                $capturedContext = $ctx;
                return [];
            });

        $this->saleProvider->provide($operation, [], $context);

        return $capturedContext;
    }

    // ── collection — type=inbound ─────────────────────────────────────────────

    public function testProvideCollectionWithTypeInboundAddsInboundFilters(): void
    {
        $op = new GetCollection();
        $ctx = $this->captureCollectionContext($op, ['filters' => ['type' => 'inbound']]);

        $filter = $ctx['filters']['filter'] ?? null;
        $this->assertIsArray($filter);
        // Filter should contain tipo_id = 1
        $this->assertContains(['tipo_id', '=', 1], $filter);
    }

    public function testProvideCollectionWithTypePurchasesAddsInboundFilters(): void
    {
        $op = new GetCollection();
        $ctx = $this->captureCollectionContext($op, ['filters' => ['type' => 'purchases']]);

        $filter = $ctx['filters']['filter'] ?? null;
        $this->assertIsArray($filter);
        // Same as inbound: tipo_id = 1
        $this->assertContains(['tipo_id', '=', 1], $filter);
    }

    // ── collection — type=outbound ────────────────────────────────────────────

    public function testProvideCollectionWithTypeOutboundAddsOutboundFilters(): void
    {
        $op = new GetCollection();
        $ctx = $this->captureCollectionContext($op, ['filters' => ['type' => 'outbound']]);

        $filter = $ctx['filters']['filter'] ?? null;
        $this->assertIsArray($filter);
        // Filter should contain tipo_id = 2
        $this->assertContains(['tipo_id', '=', 2], $filter);
    }

    // ── collection — type=null/other → sale filters ───────────────────────────

    public function testProvideCollectionWithNullTypeAddsSaleFilters(): void
    {
        $op = new GetCollection();
        $ctx = $this->captureCollectionContext($op, ['filters' => []]);

        $filter = $ctx['filters']['filter'] ?? null;
        $this->assertIsArray($filter);
        // Sale filters: number > 0 AND tipo_id = 2
        $this->assertContains(['number', '>', 0], $filter);
        $this->assertContains(['tipo_id', '=', 2], $filter);
    }

    public function testProvideCollectionWithUnknownTypeAddsSaleFilters(): void
    {
        $op = new GetCollection();
        $ctx = $this->captureCollectionContext($op, ['filters' => ['type' => 'unknown']]);

        $filter = $ctx['filters']['filter'] ?? null;
        $this->assertIsArray($filter);
        $this->assertContains(['number', '>', 0], $filter);
    }

    // ── item operation ────────────────────────────────────────────────────────

    public function testProvideItemOperationCallsItemProvider(): void
    {
        $op = new Get();
        $expectedResult = new \stdClass();

        $this->itemProvider->expects($this->once())
            ->method('provide')
            ->with($op, ['id' => 'sale-uuid'], [])
            ->willReturn($expectedResult);

        $this->collectionProvider->expects($this->never())->method('provide');

        $result = $this->saleProvider->provide($op, ['id' => 'sale-uuid'], []);

        $this->assertSame($expectedResult, $result);
    }

    // ── addSort() tested via collection provide ───────────────────────────────

    public function testAddSortAddsDescSortWhenNoExistingSort(): void
    {
        $op = new GetCollection();
        $ctx = $this->captureCollectionContext($op, ['filters' => []]);

        $sort = $ctx['filters']['sort'] ?? null;
        $this->assertIsArray($sort);
        $this->assertNotEmpty($sort);

        $sortEntry = $sort[0];
        $this->assertSame('date_created', $sortEntry['selector']);
        $this->assertTrue($sortEntry['desc']);
    }

    // ── addFilters() — merges when existing filters present ───────────────────

    public function testAddFiltersMergesWithExistingFiltersAsNestedAndArray(): void
    {
        $op = new GetCollection();
        // Pre-populate existing filters
        $existingFilter = ['some_field', '=', 'value'];
        $ctx = $this->captureCollectionContext($op, ['filters' => ['filter' => $existingFilter]]);

        $filter = $ctx['filters']['filter'] ?? null;
        $this->assertIsArray($filter);
        // Merged structure: [$existingFilter, 'and', $newFilters]
        $this->assertSame($existingFilter, $filter[0]);
        $this->assertSame('and', $filter[1]);
        $this->assertIsArray($filter[2]);
    }
}
