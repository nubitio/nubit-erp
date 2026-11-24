<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Controller;

use App\CashRegister\Controller\CashRegisterReportController;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Export\PdfExporter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

/**
 * Integration test for CashRegisterReportController — previously 0/76
 * statements covered (SLICE-TEST cash-register coverage push, #314).
 * PdfExporter shells out to a real weasyprint binary, so it's replaced with
 * a stub that captures the rendered HTML instead of actually producing a PDF.
 */
uses(KernelTestCase::class);

function insertClosureReportFixtures(Connection $connection): int
{
    $suffix = random_int(100_000, 999_999);
    $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

    $localId = 'local-' . $suffix;
    $connection->executeStatement('INSERT INTO local (local_id, empresa_id, descripcion) VALUES (:id, :empresaId, :desc)', [
        'id' => $localId,
        'empresaId' => 'empresa-' . $suffix,
        'desc' => 'Sucursal de prueba',
    ]);

    $connection->executeStatement('INSERT INTO usuario (rol_id, username, password) VALUES (2, :username, :password)', [
        'username' => 'report-test-' . $suffix,
        'password' => bin2hex(random_bytes(4)),
    ]);
    $userId = (int) $connection->lastInsertId();

    $connection->executeStatement('INSERT INTO cash_register (branch_id, name) VALUES (:branchId, :name)', [
        'branchId' => $localId,
        'name' => 'Caja Principal',
    ]);
    $cashRegisterId = (int) $connection->lastInsertId();

    $connection->executeStatement(
        'INSERT INTO cash_sessions (cash_register_id, branch_id, opened_by, status, opening_amount, expected_amount, counted_amount, difference_amount, opened_at, closed_at, close_note)
         VALUES (:cashRegisterId, :branchId, :openedBy, :status, :opening, :expected, :counted, :difference, NOW(), NOW(), :note)',
        [
            'cashRegisterId' => $cashRegisterId,
            'branchId' => $localId,
            'openedBy' => $userId,
            'status' => 'CLOSED',
            'opening' => '100.00',
            'expected' => '150.00',
            'counted' => '150.00',
            'difference' => '0.00',
            'note' => 'Cierre de prueba',
        ],
    );
    $sessionId = (int) $connection->lastInsertId();

    $connection->executeStatement("INSERT INTO payment_method (name) VALUES ('Efectivo test {$suffix}')");
    $paymentMethodId = (int) $connection->lastInsertId();

    $connection->executeStatement("INSERT INTO cash_movement_category (code, name, type) VALUES (:code, 'Venta', 'income')", [
        'code' => 'SALE_TEST_' . $suffix,
    ]);
    $categoryId = (int) $connection->lastInsertId();

    $connection->executeStatement(
        'INSERT INTO cash_movements (cash_session_id, movement_type, category_id, payment_method_id, amount, description, created_by, created_at)
         VALUES (:sessionId, :type, :categoryId, :paymentMethodId, :amount, :description, :createdBy, NOW())',
        [
            'sessionId' => $sessionId,
            'type' => 'SALE_IN',
            'categoryId' => $categoryId,
            'paymentMethodId' => $paymentMethodId,
            'amount' => '50.00',
            'description' => 'Venta de prueba',
            'createdBy' => $userId,
        ],
    );
    $connection->executeStatement(
        'INSERT INTO cash_movements (cash_session_id, movement_type, category_id, payment_method_id, amount, description, created_by, created_at)
         VALUES (:sessionId, :type, :categoryId, :paymentMethodId, :amount, :description, :createdBy, NOW())',
        [
            'sessionId' => $sessionId,
            'type' => 'CASH_OUT',
            'categoryId' => $categoryId,
            'paymentMethodId' => $paymentMethodId,
            'amount' => '20.00',
            'description' => 'Retiro de prueba',
            'createdBy' => $userId,
        ],
    );

    return $sessionId;
}

beforeEach(function (): void {
    self::bootKernel();
    $this->connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
    $this->twig = static::getContainer()->get(Environment::class);
});

it('returns a 404 when the session does not exist', function (): void {
    $pdfExporter = $this->createStub(PdfExporter::class);

    $controller = new CashRegisterReportController($this->twig, $this->connection, $pdfExporter);
    $controller->setContainer(static::getContainer());

    $response = $controller->__invoke(999_888_777);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
});

it('reads the shift from the shared report and exports the rendered closure report as a PDF', function (): void {
    $sessionId = insertClosureReportFixtures($this->connection);

    $capturedContent = null;
    $capturedFilename = null;

    $pdfExporter = $this->createMock(PdfExporter::class);
    $pdfExporter
        ->expects(self::once())
        ->method('export')
        ->willReturnCallback(function (string $content, string $filename) use (
            &$capturedContent,
            &$capturedFilename,
        ): StreamedResponse {
            $capturedContent = $content;
            $capturedFilename = $filename;

            return new StreamedResponse(static function () use ($content): void {
                echo $content;
            });
        });

    $controller = new CashRegisterReportController($this->twig, $this->connection, $pdfExporter);
    $controller->setContainer(static::getContainer());

    $response = $controller->__invoke($sessionId);

    expect($response)
        ->toBeInstanceOf(StreamedResponse::class)
        ->and($capturedFilename)
        ->toBe("cierre-caja-{$sessionId}")
        ->and($capturedContent)
        ->toContain('50.00') // income
        ->and($capturedContent)
        ->toContain('20.00') // expense
        ->and($capturedContent)
        ->toContain('Sucursal de prueba') // the branch, from the session
        ->and($capturedContent)
        ->toContain('Retiro de prueba'); // the expense listed on the second page
});
