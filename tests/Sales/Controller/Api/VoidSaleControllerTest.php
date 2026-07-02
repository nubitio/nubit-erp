<?php

declare(strict_types=1);

namespace App\Tests\Sales\Controller\Api;

use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Sales\Controller\Api\VoidSale;
use App\Sales\Port\SaleVoiderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VoidSaleControllerTest extends TestCase
{
    public function testReturnsValidationErrorWhenReasonIsMissing(): void
    {
        $saleVoider = $this->createMock(SaleVoiderInterface::class);
        $saleVoider->expects(self::never())->method('voidSale');

        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(10, 'cashier', 3, ['ROLE_USER']));

        $controller = new VoidSale($saleVoider, $security);
        $request = Request::create('/api/sales/sale-1/void', 'POST');
        $request->attributes->set('id', 'sale-1');
        $response = $controller($request);

        $payload = json_decode((string) $response->getContent(), true);
        self::assertFalse($payload['success']);
        self::assertSame('Debe indicar el motivo de anulación', $payload['message']);
    }
}
