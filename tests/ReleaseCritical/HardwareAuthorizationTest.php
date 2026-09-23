<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use App\Billing\Controller\PrintESCPOSController;
use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Repository\TaxDocumentRepository;
use App\Efact\Contract\LogoProviderInterface;
use App\Settings\Service\TenantSettingsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;

final class HardwareAuthorizationTest extends TestCase
{
    public function testPrinterInvocationProducesEscPosCommandsForARealVoucherFixture(): void
    {
        $voucher = new Voucher(
            sale_id: 'sale-hardware-fixture',
            customer_name: 'Cliente de prueba',
            company_name: 'Empresa de prueba',
            voucher_type: '03',
            voucher_series: 'T001',
            voucher_number: '00000001',
            voucher_payment_method_name: 'CONTADO',
            voucher_date: '2026-01-15',
            voucher_currency: 'PEN',
            voucher_taxed_amount: '84.75',
            voucher_tax_amount: '15.25',
            voucher_sale_amount: '100.00',
            voucher_qr_data: '',
            voucher_items: [],
        );

        $repository = $this->createStub(TaxDocumentRepository::class);
        $repository->method('getVoucher')->willReturn($voucher);

        $settings = $this->createStub(TenantSettingsService::class);
        $settings->method('isPrintLogo')->willReturn(false);
        $settings->method('getRc')->willReturn(0.0);
        $settings->method('getIgv')->willReturn(18.0);

        $controller = new PrintESCPOSController(
            $this->createStub(LogoProviderInterface::class),
            $repository,
            $settings,
            48,
        );

        $response = $controller->__invoke(
            'sale-hardware-fixture',
            Request::create('https://demo.example.test/api/sales/sale-hardware-fixture/print'),
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        static::assertNotFalse($content);
        static::assertNotEmpty($content);
        static::assertStringContainsString('Empresa de prueba', $content);
    }

    public function testPrinterControllerRejectsAnUnauthenticatedRuntimeInvocation(): void
    {
        $controller = new PrintESCPOSController(
            $this->createStub(LogoProviderInterface::class),
            $this->createStub(TaxDocumentRepository::class),
            $this->createStub(TenantSettingsService::class),
            48,
        );
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->expects(static::once())->method('isGranted')->with('ROLE_USER')->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            [$controller, '__invoke'],
            ['id' => 'sale-hardware-fixture', 'request' => Request::create('/print')],
            Request::create('/print'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        static::expectException(AccessDeniedException::class);
        new IsGrantedAttributeListener($authorization)->onKernelControllerArguments($event);
    }
}
