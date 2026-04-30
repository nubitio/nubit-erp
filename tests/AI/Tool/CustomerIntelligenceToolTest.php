<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Context\BranchContextInterface;
use App\AI\Tool\CustomerIntelligenceTool;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CustomerIntelligenceToolTest extends TestCase
{
    private Connection&MockObject $connection;
    private BranchContextInterface&MockObject $branchContextService;
    private CustomerIntelligenceTool $tool;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->branchContextService = $this->createMock(BranchContextInterface::class);
        $this->branchContextService->method('getCurrentBranchId')->willReturn(null);

        $this->tool = new CustomerIntelligenceTool($this->connection, $this->branchContextService);
    }

    /**
     * AC-CI-01: Returns error when customer is not found.
     */
    public function testNotFoundReturnsError(): void
    {
        // fetchAssociative for customer lookup returns false
        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->tool->customerIntelligence('12345678'); // 8-digit DNI not found

        self::assertArrayHasKey('error', $result);
        self::assertSame('Cliente no encontrado', $result['error']);
    }

    /**
     * AC-CI-02: Non-numeric identifier returns error immediately.
     */
    public function testNonNumericIdentifierReturnsError(): void
    {
        // Should not call DB at all for non-numeric input
        $this->connection
            ->expects(self::never())
            ->method('fetchAssociative');

        $result = $this->tool->customerIntelligence('INVALID-ABC');

        self::assertArrayHasKey('error', $result);
        self::assertSame('Cliente no encontrado', $result['error']);
    }

    /**
     * AC-CI-03a: Correctly classifies RUC (11-digit identifier).
     */
    public function testCorrectlyClassifiesRuc(): void
    {
        $this->setupSuccessfulCustomer('20123456789', 'Empresa SAC');

        $result = $this->tool->customerIntelligence('20123456789');

        self::assertArrayNotHasKey('error', $result);
        self::assertSame('RUC', $result['document_type']);
        self::assertSame('20123456789', $result['document_number']);
    }

    /**
     * AC-CI-03b: Correctly classifies DNI (8-digit identifier).
     */
    public function testCorrectlyClassifiesDni(): void
    {
        $this->setupSuccessfulCustomer('12345678', 'Juan Perez');

        $result = $this->tool->customerIntelligence('12345678');

        self::assertArrayNotHasKey('error', $result);
        self::assertSame('DNI', $result['document_type']);
        self::assertSame('12345678', $result['document_number']);
    }

    /**
     * AC-CI-04: Frequency label — 'frecuente' when >= 1 purchase/month avg.
     */
    public function testFrequencyLabelFrecuente(): void
    {
        // 30 purchases over ~12 months ≈ 2.5/month => frecuente
        $this->setupCustomerWithStats(
            ruc: '20123456789',
            businessName: 'Empresa SAC',
            totalPurchases: 30,
            totalSpent: '3000.00',
            firstDate: '2025-01-01 00:00:00',
            lastDate: '2025-12-31 00:00:00',
        );

        $result = $this->tool->customerIntelligence('20123456789');

        self::assertArrayNotHasKey('error', $result);
        self::assertSame('frecuente', $result['frequency']);
    }

    /**
     * AC-CI-05: Frequency label — 'ocasional' when >= 1/quarter but < 1/month.
     */
    public function testFrequencyLabelOcasional(): void
    {
        // 6 purchases over ~12 months ≈ 0.5/month — above 1/quarter (0.33) but below 1/month
        $this->setupCustomerWithStats(
            ruc: '20123456789',
            businessName: 'Empresa SAC',
            totalPurchases: 6,
            totalSpent: '600.00',
            firstDate: '2025-01-01 00:00:00',
            lastDate: '2025-12-31 00:00:00',
        );

        $result = $this->tool->customerIntelligence('20123456789');

        self::assertArrayNotHasKey('error', $result);
        self::assertSame('ocasional', $result['frequency']);
    }

    /**
     * AC-CI-06: Frequency label — 'inactivo' when < 1/quarter.
     */
    public function testFrequencyLabelInactivo(): void
    {
        // 1 purchase over 12 months = 1/year => inactivo
        $this->setupCustomerWithStats(
            ruc: '20123456789',
            businessName: 'Empresa SAC',
            totalPurchases: 1,
            totalSpent: '100.00',
            firstDate: '2025-01-01 00:00:00',
            lastDate: '2025-12-31 00:00:00',
        );

        $result = $this->tool->customerIntelligence('20123456789');

        self::assertArrayNotHasKey('error', $result);
        self::assertSame('inactivo', $result['frequency']);
    }

    /**
     * AC-CI-07: Returns correct profile shape with all expected keys.
     */
    public function testReturnsCorrectProfileShape(): void
    {
        $this->setupCustomerWithStats(
            ruc: '20123456789',
            businessName: 'Empresa SAC',
            totalPurchases: 5,
            totalSpent: '500.00',
            firstDate: '2025-06-01 10:00:00',
            lastDate: '2025-11-01 10:00:00',
        );

        $result = $this->tool->customerIntelligence('20123456789');

        self::assertArrayNotHasKey('error', $result);
        self::assertArrayHasKey('customer_id', $result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('document_type', $result);
        self::assertArrayHasKey('document_number', $result);
        self::assertArrayHasKey('total_purchases', $result);
        self::assertArrayHasKey('total_spent', $result);
        self::assertArrayHasKey('avg_ticket', $result);
        self::assertArrayHasKey('first_purchase_date', $result);
        self::assertArrayHasKey('last_purchase_date', $result);
        self::assertArrayHasKey('top_products', $result);
        self::assertArrayHasKey('frequency', $result);
        self::assertArrayHasKey('days_since_last_purchase', $result);

        self::assertSame(5, $result['total_purchases']);
        self::assertSame(500.0, $result['total_spent']);
        self::assertSame(100.0, $result['avg_ticket']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Setup mocks for a successful customer lookup that has one purchase.
     */
    private function setupSuccessfulCustomer(string $ruc, string $businessName): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                // First call: customer lookup
                ['id' => 1, 'ruc' => $ruc, 'razon_social' => $businessName],
                // Second call: stats (one purchase so we don't hit the "not found" early return)
                ['total_purchases' => 1, 'total_spent' => '100.00', 'first_purchase_date' => '2025-01-01 00:00:00', 'last_purchase_date' => '2025-01-01 00:00:00'],
            );

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);
    }

    /**
     * Setup mocks for a customer with purchase statistics.
     */
    private function setupCustomerWithStats(
        string $ruc,
        string $businessName,
        int $totalPurchases,
        string $totalSpent,
        string $firstDate,
        string $lastDate,
    ): void {
        $this->connection
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                // First call: customer lookup
                ['id' => 1, 'ruc' => $ruc, 'razon_social' => $businessName],
                // Second call: stats
                [
                    'total_purchases' => $totalPurchases,
                    'total_spent' => $totalSpent,
                    'first_purchase_date' => $firstDate,
                    'last_purchase_date' => $lastDate,
                ],
            );

        // Third call: top products
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);
    }
}
