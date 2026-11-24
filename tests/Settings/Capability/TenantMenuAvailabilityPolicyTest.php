<?php

declare(strict_types=1);

namespace App\Tests\Settings\Capability;

use App\Settings\Capability\TenantMenuAvailabilityPolicy;
use PHPUnit\Framework\TestCase;

final class TenantMenuAvailabilityPolicyTest extends TestCase
{
    public function testRestaurantRouteRequiresTheRestaurantVerticalAndCapability(): void
    {
        $policy = new TenantMenuAvailabilityPolicy();
        $withEntitlement = [
            'profile' => ['vertical' => 'retail'],
            'capabilities' => [
                'restaurant.table_management' => ['enabled' => true],
            ],
        ];

        static::assertFalse($policy->allows('/restaurant/mesas', $withEntitlement));
        static::assertFalse($policy->allows('/restaurant/mesas', [
            'profile' => ['vertical' => 'restaurant'],
            'capabilities' => [],
        ]));
        static::assertTrue($policy->allows('/restaurant/mesas', [
            'profile' => ['vertical' => 'restaurant'],
            'capabilities' => [
                'restaurant.table_management' => ['enabled' => true],
            ],
        ]));
    }

    public function testPaidModulesAreHiddenWhenTheirCapabilityIsUnavailable(): void
    {
        $policy = new TenantMenuAvailabilityPolicy();

        static::assertFalse($policy->allows('/employees', ['capabilities' => []]));
        static::assertTrue($policy->allows('/employees', [
            'capabilities' => ['payroll.core' => ['enabled' => true]],
        ]));
        static::assertTrue($policy->allows('/catalog', ['capabilities' => []]));
    }
}
