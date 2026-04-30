<?php

declare(strict_types=1);

namespace App\Tests\EInvoicing\Adapter;

use Nubit\Platform\Tenant\Context\TenantContext;
use App\Efact\Http\ApiResponse;
use App\EInvoicing\Adapter\SaleInvoicingAdapter;
use App\EInvoicing\Message\SendInvoiceMessage;
use App\EInvoicing\Controller\PrintESCPOSController;
use App\EInvoicing\Sale\SaleVoidUseCase;
use App\EInvoicing\Service\SaleV2Loader;
use App\Sales\State\DetractionCalculatorInterface;
use App\EInvoicing\Service\InvoiceCreationServiceInterface;
use App\Sales\Entity\SaleRecord;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class SaleInvoicingAdapterTest extends TestCase
{
    public function testCreateAndDispatchSkipsMessageWhenCreationFails(): void
    {
        $invoiceCreation = $this->createStub(InvoiceCreationServiceInterface::class);
        $invoiceCreation->method('createForSale')->willReturn(['success' => false, 'message' => 'not ready']);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $result = $this->adapter($invoiceCreation, $messageBus)->createAndDispatchInvoiceForSaleId('sale-1');

        self::assertSame(['success' => false, 'message' => 'not ready'], $result);
    }

    public function testCreateAndDispatchSendsMessageAfterCurrentBusWhenCreationSucceeds(): void
    {
        $invoiceCreation = $this->createStub(InvoiceCreationServiceInterface::class);
        $invoiceCreation->method('createForSale')->willReturn(['success' => true, 'invoiceId' => 'tax-1']);

        $capturedMessage = null;
        $capturedStamps = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(function (mixed $message) use (&$capturedMessage): bool {
                    $capturedMessage = $message;

                    return $message instanceof SendInvoiceMessage;
                }),
                self::callback(function (mixed $stamps) use (&$capturedStamps): bool {
                    $capturedStamps = $stamps;

                    return is_array($stamps);
                }),
            )
            ->willReturn(new Envelope(new SendInvoiceMessage('tax-1', '123')));

        $result = $this->adapter($invoiceCreation, $messageBus, tenantId: 123)
            ->createAndDispatchInvoiceForSaleId('sale-1');

        self::assertSame(['success' => true, 'invoiceId' => 'tax-1'], $result);
        self::assertInstanceOf(SendInvoiceMessage::class, $capturedMessage);
        self::assertSame('tax-1', $capturedMessage->taxDocumentId);
        self::assertSame('123', $capturedMessage->getTenantId());
        self::assertIsArray($capturedStamps);
        self::assertContainsOnlyInstancesOf(DispatchAfterCurrentBusStamp::class, $capturedStamps);
    }

    public function testVoidSaleDelegatesToUseCase(): void
    {
        $invoiceCreation = $this->createStub(InvoiceCreationServiceInterface::class);
        $messageBus = $this->createStub(MessageBusInterface::class);
        $voidUseCase = $this->createMock(SaleVoidUseCase::class);
        $voidUseCase->expects(self::once())
            ->method('__invoke')
            ->with('sale-1', 'reason', 10)
            ->willReturn(new ApiResponse(true, 'ok'));

        $response = $this->adapter($invoiceCreation, $messageBus, voidUseCase: $voidUseCase)
            ->voidSale('sale-1', 'reason', 10);

        self::assertSame('{"success":true,"message":"ok","data":null}', $response->getContent());
    }

    private function adapter(
        InvoiceCreationServiceInterface $invoiceCreation,
        MessageBusInterface $messageBus,
        int $tenantId = 123,
        ?SaleVoidUseCase $voidUseCase = null,
    ): SaleInvoicingAdapter {
        $tenantContext = new TenantContext();
        $tenantContext->setTenant($tenantId, 'tenant', 'tenant.test', 'request-1');

        $saleV2Loader = $this->createMock(SaleV2Loader::class);
        $saleV2Loader->method('loadAsSale')->willReturn(new SaleRecord());

        return new SaleInvoicingAdapter(
            $invoiceCreation,
            $messageBus,
            $tenantContext,
            $voidUseCase ?? $this->createMock(SaleVoidUseCase::class),
            $saleV2Loader,
            $this->createMock(DetractionCalculatorInterface::class),
            $this->createMock(PrintESCPOSController::class),
        );
    }
}
