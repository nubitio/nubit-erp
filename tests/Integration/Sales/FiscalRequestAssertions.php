<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\Integration\Sales\SaleFiscalRequests;
use App\Integration\Security\ApiClientUser;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class FiscalRequestAssertions
{
    public static function verify(
        SaleFiscalRequests $fiscal,
        ApiClientUser $principal,
        Connection $db,
        string $sale,
        string $branch,
    ): void {
        try {
            $fiscal->request($principal, $sale);
            Assert::fail('Unpaid sales are not eligible.');
        } catch (UnprocessableEntityHttpException $e) {
            Assert::assertSame(422, $e->getStatusCode());
        }

        foreach (['01' => 'F', '03' => 'B'] as $type => $prefix) {
            $db->executeStatement('INSERT IGNORE INTO tipo_comprobante (codigo, descripcion) VALUES (?, ?)', [
                $type,
                $prefix,
            ]);
            $db->insert('local_comprobante', [
                'local_id' => $branch,
                'tipo_comprobante_id' => $type,
                'series' => $prefix . random_int(100, 999),
            ]);
        }
        $db->update('sales', ['balance_due' => '0.00', 'paid_total' => '20.00'], ['id' => $sale]);
        $first = $fiscal->request($principal, $sale);
        Assert::assertSame('queued', $first['status']);
        Assert::assertSame($first, $fiscal->request($principal, $sale));
        Assert::assertSame($sale, $db->fetchOne('SELECT sale_id FROM tax_document WHERE tax_document_id = ?', [
            $first['document_id'],
        ]));
        $db->update('tax_document', ['sunat_status' => '0'], ['tax_document_id' => $first['document_id']]);
        Assert::assertSame('accepted', $fiscal->find($principal, $sale)['status'] ?? null);
    }
}
