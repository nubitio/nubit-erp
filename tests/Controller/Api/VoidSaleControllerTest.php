<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Sales\Controller\Api\VoidSale;
use App\Core\Application\Session\Security;
use App\Identity\Dto\AuthUserDto;
use App\EInvoicing\Sale\SaleVoidUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VoidSaleControllerTest extends TestCase
{
    public function testReturnsValidationErrorWhenReasonIsMissing(): void
    {
        $saleVoidUseCase = $this->createMock(SaleVoidUseCase::class);
        $saleVoidUseCase->expects(self::never())->method('__invoke');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(10, 'cashier', 3, ['ROLE_USER']));

        $controller = new VoidSale($saleVoidUseCase, $security);
        $request = Request::create('/api/sales/sale-1/void', 'POST');
        $request->attributes->set('id', 'sale-1');
        $response = $controller($request);

        $payload = json_decode((string) $response->getContent(), true);
        self::assertFalse($payload['success']);
        self::assertSame('Debe indicar el motivo de anulación', $payload['message']);
    }
}
