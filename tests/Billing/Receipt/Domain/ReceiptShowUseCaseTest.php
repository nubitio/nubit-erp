<?php

declare(strict_types=1);

namespace App\Tests\Billing\Receipt\Domain;

use App\Billing\Entity\TaxDocument;
use App\Billing\Receipt\Domain\Receipt;
use App\Billing\Receipt\Domain\ReceiptCdrUseCase;
use App\Billing\Receipt\Domain\ReceiptRequest;
use App\Billing\Receipt\Domain\ReceiptShowUseCase;
use App\Billing\Receipt\Domain\ReceiptType;
use App\Billing\Repository\TaxDocumentRepository;
use App\Organization\Service\CompanyContext;
use Nubit\Platform\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

final class ReceiptShowUseCaseTest extends TestCase
{
    private function makeDocument(string $docType, string $series, string $number, float $total): TaxDocument
    {
        $doc = new TaxDocument();
        $doc->setDocumentType($docType);
        $doc->setSeries($series);
        $doc->setNumber($number);
        $doc->setTotal((string) $total);
        $doc->setCurrency('PEN');
        $doc->setIssueDate(new \DateTimeImmutable('2026-04-30'));
        return $doc;
    }

    public function testReturnsReceiptForFactura(): void
    {
        $doc = $this->makeDocument('01', 'F001', '00042', 250.00);

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('find')->willReturn($doc);

        $context = $this->createStub(CompanyContext::class);
        $context->method('getName')->willReturn('Mi Empresa SAC');

        $useCase = new ReceiptShowUseCase($repo, $context);
        $result = $useCase(new ReceiptRequest(ReceiptType::FACTURA, 'doc-1'));

        self::assertInstanceOf(Receipt::class, $result);
        self::assertSame('FACTURA', $result->type);
        self::assertSame('F001', $result->series);
        self::assertSame('00042', $result->number);
        self::assertSame(250.0, $result->total);
        self::assertSame('Mi Empresa SAC', $result->company);
        self::assertSame('PEN', $result->currency);
    }

    public function testReturnsReceiptForBoleta(): void
    {
        $doc = $this->makeDocument('03', 'B001', '00001', 99.00);

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('find')->willReturn($doc);

        $context = $this->createStub(CompanyContext::class);
        $context->method('getName')->willReturn('Mi Empresa SAC');

        $useCase = new ReceiptShowUseCase($repo, $context);
        $result = $useCase(new ReceiptRequest(ReceiptType::BOLETA, 'doc-2'));

        self::assertSame('BOLETA', $result->type);
    }

    public function testReturnsReceiptForNotaCredito(): void
    {
        $doc = $this->makeDocument('07', 'FC01', '00003', 50.00);

        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('find')->willReturn($doc);

        $context = $this->createStub(CompanyContext::class);
        $context->method('getName')->willReturn('Mi Empresa SAC');

        $useCase = new ReceiptShowUseCase($repo, $context);
        $result = $useCase(new ReceiptRequest(ReceiptType::NOTA_CREDITO, 'doc-3'));

        self::assertSame('NOTA DE CRÉDITO', $result->type);
    }

    public function testThrowsNotFoundExceptionWhenDocumentMissing(): void
    {
        $repo = $this->createStub(TaxDocumentRepository::class);
        $repo->method('find')->willReturn(null);

        $context = $this->createStub(CompanyContext::class);

        $useCase = new ReceiptShowUseCase($repo, $context);

        $this->expectException(NotFoundException::class);
        $useCase(new ReceiptRequest(ReceiptType::FACTURA, 'missing-id'));
    }
}
