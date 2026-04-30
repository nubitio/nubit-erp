<?php

declare(strict_types=1);

namespace App\Tests\Purchasing;

use App\Purchasing\State\PurchasesWritePolicy;
use PHPUnit\Framework\TestCase;

final class PurchasingDomainTest extends TestCase
{
    public function testPurchasesWritePolicyHoldsAllFields(): void
    {
        $policy = new PurchasesWritePolicy(
            mode: 'v2_only',
            writeNewSchema: true,
            skipLegacyPersist: true,
            strict: false,
        );

        self::assertSame('v2_only', $policy->mode);
        self::assertTrue($policy->writeNewSchema);
        self::assertTrue($policy->skipLegacyPersist);
        self::assertFalse($policy->strict);
    }

    public function testPurchasesWritePolicyDualModeDefaults(): void
    {
        $policy = new PurchasesWritePolicy(
            mode: 'dual',
            writeNewSchema: true,
            skipLegacyPersist: false,
            strict: true,
        );

        self::assertSame('dual', $policy->mode);
        self::assertFalse($policy->skipLegacyPersist);
        self::assertTrue($policy->strict);
    }
}
