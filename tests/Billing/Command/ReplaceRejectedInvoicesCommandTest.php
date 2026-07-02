<?php

declare(strict_types=1);

namespace App\Tests\Billing\Command;

use App\Billing\Command\ReplaceRejectedInvoicesCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReplaceRejectedInvoicesCommandTest extends TestCase
{
    public function testBuildReplacementDocumentRowResetsSunatDeliveryFields(): void
    {
        $old = [
            'tax_document_id' => 'old-id',
            'movimiento_id' => 'movement-id',
            'sale_id' => 'sale-id',
            'document_type' => '01',
            'issue_date' => '2026-05-19 10:00:00',
            'series' => 'F001',
            'number' => '123',
            'currency' => 'PEN',
            'subtotal' => '100.00',
            'taxable_amount' => '100.00',
            'igv_amount' => '18.00',
            'rc_amount' => '0.00',
            'total' => '118.00',
            'discount_percent' => '0.00',
            'discount_amount' => '0.00',
            'sunat_status' => '3462',
            'sunat_message' => 'Rejected by SUNAT',
            'digest' => 'old-digest',
            'cdr_path' => 'cdr/old.zip',
            'void_ticket' => 'old-ticket',
            'void_correlation' => 'old-correlation',
            'sent_at' => '2026-05-19 10:05:00',
            'accepted_at' => '2026-05-19 10:06:00',
            'created_at' => '2026-05-19 10:00:00',
            'updated_at' => '2026-05-19 10:06:00',
        ];

        $row = $this->callBuildReplacementDocumentRow($old, 'new-id', '124', '2026-05-20 11:00:00');

        self::assertSame('new-id', $row['tax_document_id']);
        self::assertSame('2026-05-20 11:00:00', $row['issue_date']);
        self::assertSame('124', $row['number']);
        self::assertSame('pendiente', $row['sunat_status']);
        self::assertSame('Replacement for rejected document F001-123', $row['sunat_message']);
        self::assertNull($row['digest']);
        self::assertNull($row['cdr_path']);
        self::assertNull($row['void_ticket']);
        self::assertNull($row['void_correlation']);
        self::assertNull($row['sent_at']);
        self::assertNull($row['accepted_at']);
        self::assertSame('movement-id', $row['movimiento_id']);
        self::assertSame('sale-id', $row['sale_id']);
    }

    /** @return array<string, array{string, bool}> */
    public static function dateProvider(): array
    {
        return [
            'valid date' => ['2026-05-20', true],
            'invalid month' => ['2026-13-20', false],
            'invalid day' => ['2026-05-40', false],
            'datetime is rejected' => ['2026-05-20 10:00:00', false],
            'wrong format is rejected' => ['20-05-2026', false],
        ];
    }

    #[DataProvider('dateProvider')]
    public function testIsValidDate(string $date, bool $expected): void
    {
        $command = new ReplaceRejectedInvoicesCommand($this->createStub(Connection::class));
        $method = new \ReflectionMethod($command, 'isValidDate');

        self::assertSame($expected, $method->invoke($command, $date));
    }

    /**
     * @param array<string, mixed> $old
     * @return array<string, mixed>
     */
    private function callBuildReplacementDocumentRow(
        array $old,
        string $newId,
        string $newNumber,
        string $issueDate,
    ): array {
        $command = new ReplaceRejectedInvoicesCommand($this->createStub(Connection::class));
        $method = new \ReflectionMethod($command, 'buildReplacementDocumentRow');

        /** @var array<string, mixed> $row */
        $row = $method->invoke($command, $old, $newId, $newNumber, $issueDate);

        return $row;
    }
}
