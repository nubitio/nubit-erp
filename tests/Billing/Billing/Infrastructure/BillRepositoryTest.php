<?php

declare(strict_types=1);

namespace App\Tests\Billing\Billing\Infrastructure;

use Nubit\Platform\Tenant\Context\TenantContext;
use App\Billing\Sunat\Infrastructure\BillRepository;
use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[AllowMockObjectsWithoutExpectations]
final class BillRepositoryTest extends TestCase
{
    private SerializerInterface&Stub $serializer;
    private LoggerInterface&Stub $logger;
    private LoggerInterface&Stub $billingLogger;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->billingLogger = $this->createStub(LoggerInterface::class);
        $this->tenantContext = new TenantContext();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // sendBill
    // ──────────────────────────────────────────────────────────────────────────

    public function testSendBillReturnsDocumentResponse(): void
    {
        $invoice = new Invoice();

        $documentResponse = new DocumentResponse();
        $documentResponse->name = 'F001-00000001';
        $documentResponse->xml = '<xml/>';
        $documentResponse->hash = 'abc123';
        $documentResponse->sunatResponse = (new BillResult())->setSuccess(true);

        $this->serializer->method('serialize')
            ->willReturn('{"json":"body"}');

        $this->serializer->method('deserialize')
            ->with(self::anything(), DocumentResponse::class, 'json')
            ->willReturn($documentResponse);

        $httpClient = new MockHttpClient(
            new MockResponse('{"name":"F001-00000001","xml":"<xml/>","hash":"abc123","sunatResponse":{"success":true}}')
        );

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'test-token',
        );

        $result = $repo->sendBill($invoice);

        self::assertSame($documentResponse, $result);
    }

    public function testSendBillLogsInfoMessages(): void
    {
        $invoice = new Invoice();

        $documentResponse = new DocumentResponse();
        $documentResponse->name = 'F001-00000001';
        $documentResponse->xml = '<xml/>';
        $documentResponse->hash = 'hash';
        $documentResponse->sunatResponse = (new BillResult())->setSuccess(true);

        $this->serializer->method('serialize')->willReturn('{}');
        $this->serializer->method('deserialize')->willReturn($documentResponse);

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(3))
            ->method('info');

        $httpClient = new MockHttpClient(new MockResponse('{}'));

        $repo = new BillRepository(
            $this->serializer,
            $logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'test-token',
        );

        $repo->sendBill($invoice);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getXml
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetXmlReturnsContent(): void
    {
        $invoice = new Invoice();

        $this->serializer->method('serialize')->willReturn('{"json":"body"}');

        $httpClient = new MockHttpClient(new MockResponse('<xml>content</xml>'));

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'test-token',
        );

        $result = $repo->getXml($invoice);

        self::assertSame('<xml>content</xml>', $result);
    }

    public function testGetXmlSendsPostRequestToCorrectUrl(): void
    {
        $invoice = new Invoice();
        $this->serializer->method('serialize')->willReturn('{}');

        $capturedRequest = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequest): MockResponse {
            $capturedRequest = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('xml-content');
        });

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'my-token',
        );

        $result = $repo->getXml($invoice);

        self::assertSame('xml-content', $result);
        self::assertNotNull($capturedRequest);
        self::assertSame('POST', $capturedRequest['method']);
        self::assertStringContainsString('invoice/xml?token=my-token', $capturedRequest['url']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getPdf
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetPdfReturnsContentForContadoPayment(): void
    {
        $invoice = (new Invoice())->setFormaPago(new FormaPagoContado());
        $invoice->setObservacion('');

        $this->serializer->method('serialize')->willReturn('{}');

        $httpClient = new MockHttpClient(new MockResponse('%PDF-1.4 content'));

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'test-token',
        );

        $result = $repo->getPdf($invoice);

        self::assertSame('%PDF-1.4 content', $result);
    }

    public function testGetPdfReturnsContentForCreditoPayment(): void
    {
        $invoice = new Invoice();
        $credito = new FormaPagoCredito();
        $invoice->setFormaPago($credito);
        $invoice->setObservacion('');

        $this->serializer->method('serialize')->willReturn('{}');

        $capturedBody = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedBody): MockResponse {
            $capturedBody = $options['body'] ?? null;

            return new MockResponse('%PDF-credito');
        });

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'test-token',
        );

        $result = $repo->getPdf($invoice);

        self::assertSame('%PDF-credito', $result);
    }

    public function testGetPdfSendsPostRequestToCorrectUrl(): void
    {
        $invoice = (new Invoice())->setFormaPago(new FormaPagoContado());
        $invoice->setObservacion('');

        $this->serializer->method('serialize')->willReturn('{}');

        $capturedRequest = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequest): MockResponse {
            $capturedRequest = ['method' => $method, 'url' => $url];

            return new MockResponse('pdf-bytes');
        });

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'tok',
        );

        $repo->getPdf($invoice);

        self::assertNotNull($capturedRequest);
        self::assertSame('POST', $capturedRequest['method']);
        self::assertStringContainsString('invoice/pdf?token=tok', $capturedRequest['url']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // getStatus
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetStatusReturnsStatusCdrResult(): void
    {
        $company = $this->createStub(\Greenter\Model\Company\Company::class);
        $company->method('getRuc')->willReturn('20123456789');

        $invoice = new Invoice();
        $invoice->setTipoDoc('01');
        $invoice->setSerie('F001');
        $invoice->setCorrelativo('00000001');
        $invoice->setCompany($company);

        $statusResult = (new StatusCdrResult())->setCode('0');

        $this->serializer->method('deserialize')
            ->with(self::anything(), StatusCdrResult::class, 'json')
            ->willReturn($statusResult);

        $httpClient = new MockHttpClient(new MockResponse('{"code":"0"}'));

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'test-token',
        );

        $result = $repo->getStatus($invoice);

        self::assertSame($statusResult, $result);
    }

    public function testGetStatusSendsGetRequestWithQueryParams(): void
    {
        $company = $this->createStub(\Greenter\Model\Company\Company::class);
        $company->method('getRuc')->willReturn('20999888777');

        $invoice = new Invoice();
        $invoice->setTipoDoc('01');
        $invoice->setSerie('F002');
        $invoice->setCorrelativo('00000005');
        $invoice->setCompany($company);

        $statusResult = new StatusCdrResult();
        $this->serializer->method('deserialize')->willReturn($statusResult);

        $capturedRequest = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedRequest): MockResponse {
            $capturedRequest = ['method' => $method, 'url' => $url];

            return new MockResponse('{}');
        });

        $repo = new BillRepository(
            $this->serializer,
            $this->logger,
            $this->billingLogger,
            $httpClient,
            $this->tenantContext,
            'https://api.example.com/',
            'mytoken',
        );

        $repo->getStatus($invoice);

        self::assertNotNull($capturedRequest);
        self::assertSame('GET', $capturedRequest['method']);
        self::assertStringContainsString('invoice/status?token=mytoken', $capturedRequest['url']);
        self::assertStringContainsString('ruc=20999888777', $capturedRequest['url']);
        self::assertStringContainsString('serie=F002', $capturedRequest['url']);
        self::assertStringContainsString('numero=00000005', $capturedRequest['url']);
    }
}
