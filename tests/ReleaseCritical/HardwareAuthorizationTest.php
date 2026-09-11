<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical;

use App\Billing\Controller\PrintESCPOSController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HardwareAuthorizationTest extends TestCase
{
    public function testPrinterEndpointRequiresAnAuthenticatedTenantUser(): void
    {
        $attributes = new \ReflectionClass(PrintESCPOSController::class)->getAttributes(IsGranted::class);

        static::assertCount(1, $attributes);
        static::assertSame('ROLE_USER', $attributes[0]->getArguments()[0]);
    }
}
