<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Invoice\Domain;

use App\Core\Exception\ServiceException;
use App\Core\Service\CompanyContext;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\EInvoicing\Invoice\Domain\ValidateInvoiceUseCase;
use App\EInvoicing\Invoice\Domain\Voucher;
use App\Entity\TaxDocument;
use App\Repository\TaxDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Greenter\Sunat\ConsultaCpe\Model\CpeData;
use Greenter\Sunat\ConsultaCpe\Model\CpeResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AllowMockObjectsWithoutExpectations]
final class ValidateInvoiceUseCaseTest extends TestCase
{
    private CacheInterface&MockObject $cache;
    private SettingsService&Stub $settings;
    private CompanyContext&Stub $companyContext;
    private TaxDocumentRepository&MockObject $taxDocumentRepository;
    private EntityManagerInterface&Stub $entityManager;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->settings = $this->createStub(SettingsService::class);
        $this->companyContext = $this->createStub(CompanyContext::class);
        $this->taxDocumentRepository = $this->createMock(TaxDocumentRepository::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Null taxDocument → ServiceException
    // ──────────────────────────────────────────────────────────────────────────

    public function testThrowsServiceExceptionWhenTaxDocumentNotFound(): void
    {
        $this->taxDocumentRepository
            ->method('find')
            ->with('non-existent-id')
            ->willReturn(null);

        $httpClient = new Client();

        $useCase = new ValidateInvoiceUseCase(
            $this->cache,
            $this->settings,
            $this->companyContext,
            $this->taxDocumentRepository,
            $this->entityManager,
            $httpClient,
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Invoice not found');

        $useCase('non-existent-id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Cache hit path — token already cached
    // ──────────────────────────────────────────────────────────────────────────

    public function testUsesCachedTokenAndCallsConsultaApi(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $this->taxDocumentRepository->method('find')->willReturn($taxDocument);

        $voucher = new Voucher(
            voucher_type: '01',
            voucher_series: 'F001',
            voucher_number: '00000001',
            voucher_date: '2025-01-15 00:00:00',
            voucher_sale_amount: '100.00',
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn($voucher);

        $this->companyContext->method('getId')->willReturn('20123456789');

        // Cache returns a pre-stored token — no auth HTTP call needed
        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                // Return directly from cache — callback is NOT called
                return 'cached-access-token';
            });

        // Provide a mock Guzzle stack that returns a ConsultaApi response
        $consultaResponseBody = json_encode([
            'success' => false,
            'message' => 'OK',
            'data' => null,
            'errorCode' => null,
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) $consultaResponseBody),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $useCase = new ValidateInvoiceUseCase(
            $this->cache,
            $this->settings,
            $this->companyContext,
            $this->taxDocumentRepository,
            $this->entityManager,
            $httpClient,
        );

        $result = $useCase('tax-doc-id');

        self::assertInstanceOf(CpeResponse::class, $result);
        self::assertFalse($result->getSuccess());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Full success path: auth token fetch + consultarCpe returns success + status update
    // ──────────────────────────────────────────────────────────────────────────

    public function testFullSuccessPathFetchesTokenAndUpdatesStatusWhenEstadoCpIsZero(): void
    {
        /** @var TaxDocument&MockObject $taxDocument */
        $taxDocument = $this->createMock(TaxDocument::class);
        $taxDocument->expects(self::once())->method('setSunatStatus')->with('');
        $taxDocument->expects(self::once())->method('setSunatMessage')->with('');
        $taxDocument->expects(self::once())->method('setUpdatedAt')->with(self::isInstanceOf(\DateTime::class));

        $this->taxDocumentRepository->method('find')->willReturn($taxDocument);

        $voucher = new Voucher(
            voucher_type: '01',
            voucher_series: 'F001',
            voucher_number: '00000001',
            voucher_date: '2025-01-15 00:00:00',
            voucher_sale_amount: '100.00',
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn($voucher);
        $this->companyContext->method('getId')->willReturn('20123456789');

        $this->settings->method('get')
            ->willReturnMap([
                [Constants::CPE_CLIENT_ID, null, 'client-id-value'],
                [Constants::CPE_CLIENT_SECRET, null, 'client-secret-value'],
            ]);

        // Token NOT in cache — callback will be invoked for getToken()
        $this->cache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $authTokenBody = json_encode([
            'access_token' => 'fresh-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
        $consultaSuccessBody = json_encode([
            'success' => true,
            'message' => 'OK',
            'data' => ['estadoCp' => '0'],
            'errorCode' => null,
        ]);

        $mock = new MockHandler([
            // First call: AuthApi::getToken
            new Response(200, ['Content-Type' => 'application/json'], (string) $authTokenBody),
            // Second call: ConsultaApi::consultarCpe
            new Response(200, ['Content-Type' => 'application/json'], (string) $consultaSuccessBody),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $useCase = new ValidateInvoiceUseCase(
            $this->cache,
            $this->settings,
            $this->companyContext,
            $this->taxDocumentRepository,
            $em,
            $httpClient,
        );

        $result = $useCase('tax-doc-id');

        self::assertInstanceOf(CpeResponse::class, $result);
        self::assertTrue($result->getSuccess());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Success response but estadoCp != 0 → no flush
    // ──────────────────────────────────────────────────────────────────────────

    public function testNoFlushWhenEstadoCpIsNotZero(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $this->taxDocumentRepository->method('find')->willReturn($taxDocument);

        $voucher = new Voucher(
            voucher_type: '01',
            voucher_series: 'F001',
            voucher_number: '00000001',
            voucher_date: '2025-03-01 00:00:00',
            voucher_sale_amount: '200.00',
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn($voucher);
        $this->companyContext->method('getId')->willReturn('20123456789');

        // Token is cached
        $this->cache->method('get')->willReturn('cached-token');

        $consultaResponseBody = json_encode([
            'success' => true,
            'message' => 'OK',
            'data' => ['estadoCp' => '1'],  // Not '0' — should NOT flush
            'errorCode' => null,
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) $consultaResponseBody),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $useCase = new ValidateInvoiceUseCase(
            $this->cache,
            $this->settings,
            $this->companyContext,
            $this->taxDocumentRepository,
            $em,
            $httpClient,
        );

        $result = $useCase('tax-doc-id-2');

        self::assertInstanceOf(CpeResponse::class, $result);
        self::assertTrue($result->getSuccess());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Response success=false → no flush
    // ──────────────────────────────────────────────────────────────────────────

    public function testNoFlushWhenResponseIsNotSuccessful(): void
    {
        $taxDocument = $this->createStub(TaxDocument::class);
        $this->taxDocumentRepository->method('find')->willReturn($taxDocument);

        $voucher = new Voucher(
            voucher_type: '01',
            voucher_series: 'F001',
            voucher_number: '00000001',
            voucher_date: '2025-03-01 00:00:00',
            voucher_sale_amount: '150.00',
        );
        $this->taxDocumentRepository->method('getVoucherByTaxDocumentId')->willReturn($voucher);
        $this->companyContext->method('getId')->willReturn('20123456789');

        $this->cache->method('get')->willReturn('cached-token');

        $consultaResponseBody = json_encode([
            'success' => false,
            'message' => 'Error SUNAT',
            'data' => null,
            'errorCode' => '0001',
        ]);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) $consultaResponseBody),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $useCase = new ValidateInvoiceUseCase(
            $this->cache,
            $this->settings,
            $this->companyContext,
            $this->taxDocumentRepository,
            $em,
            $httpClient,
        );

        $result = $useCase('tax-doc-id-3');

        self::assertFalse($result->getSuccess());
    }
}
