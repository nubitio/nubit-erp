<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Controller\Api\SendInvoiceEmail;
use App\Billing\Invoice\Domain\InvoiceEmailException;
use App\Billing\Invoice\Domain\InvoiceEmailUseCase;
use App\Sales\Security\SaleReadAccess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SendInvoiceEmailTest extends TestCase
{
    public function testSendsToTheAddressInTheBody(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::once())->method('__invoke')->with('doc-1', 'cliente@example.pe');

        $response = (new SendInvoiceEmail(
            $useCase,
            new NullLogger(),
            $this->createStub(SaleReadAccess::class),
        ))($this->request('doc-1', '{"email":" cliente@example.pe "}'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testFallsBackToTheCustomerAddressWithoutABody(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::once())->method('__invoke')->with('doc-1', null);

        $response = (new SendInvoiceEmail(
            $useCase,
            new NullLogger(),
            $this->createStub(SaleReadAccess::class),
        ))($this->request('doc-1', ''));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testARefusedReaderGetsA403AndNothingIsSent(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::never())->method('__invoke');
        $access = $this->createStub(SaleReadAccess::class);
        $access->method('assertCanReadTaxDocument')->willThrowException(new AccessDeniedException());

        $this->expectException(AccessDeniedException::class);

        (new SendInvoiceEmail($useCase, new NullLogger(), $access))($this->request('doc-1', ''));
    }

    public function testRejectsAnInvalidAddressWithoutSending(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::never())->method('__invoke');

        $response = (new SendInvoiceEmail(
            $useCase,
            new NullLogger(),
            $this->createStub(SaleReadAccess::class),
        ))($this->request('doc-1', '{"email":"no-es-un-correo"}'));

        self::assertSame(400, $response->getStatusCode());
    }

    public function testReportsActionableErrorsInsteadOfAGenericOne(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->method('__invoke')->willThrowException(InvoiceEmailException::missingRecipient());

        $response = (new SendInvoiceEmail(
            $useCase,
            new NullLogger(),
            $this->createStub(SaleReadAccess::class),
        ))($this->request('doc-1', ''));

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('no tiene correo', (string) $response->getContent());
    }

    private function request(string $id, string $body): Request
    {
        $request = new Request(content: $body);
        $request->attributes->set('id', $id);

        return $request;
    }
}
