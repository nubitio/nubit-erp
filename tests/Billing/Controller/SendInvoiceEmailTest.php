<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Controller\Api\SendInvoiceEmail;
use App\Billing\Invoice\Domain\InvoiceEmailUseCase;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

final class SendInvoiceEmailTest extends TestCase
{
    public function testSendsToTheAddressInTheBody(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::once())->method('__invoke')->with('doc-1', 'cliente@example.pe');

        $response = (new SendInvoiceEmail($useCase, new NullLogger()))($this->request(
            'doc-1',
            '{"email":" cliente@example.pe "}',
        ));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testFallsBackToTheCustomerAddressWithoutABody(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::once())->method('__invoke')->with('doc-1', null);

        $response = (new SendInvoiceEmail($useCase, new NullLogger()))($this->request('doc-1', ''));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testRejectsAnInvalidAddressWithoutSending(): void
    {
        $useCase = $this->createMock(InvoiceEmailUseCase::class);
        $useCase->expects(self::never())->method('__invoke');

        $response = (new SendInvoiceEmail($useCase, new NullLogger()))($this->request(
            'doc-1',
            '{"email":"no-es-un-correo"}',
        ));

        self::assertSame(400, $response->getStatusCode());
    }

    private function request(string $id, string $body): Request
    {
        $request = new Request(content: $body);
        $request->attributes->set('id', $id);

        return $request;
    }
}
