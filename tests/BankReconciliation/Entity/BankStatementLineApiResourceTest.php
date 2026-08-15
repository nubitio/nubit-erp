<?php

declare(strict_types=1);

namespace App\Tests\BankReconciliation\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\BankReconciliation\Entity\BankStatementImport;
use App\BankReconciliation\Entity\BankStatementLine;
use PHPUnit\Framework\TestCase;

/**
 * Guards against the exact bug class already hit twice on this codebase
 * (see SupplierBill's /pay operation): a custom Post operation on a
 * resource whose class-level denormalizationContext uses groups MUST
 * override it to `[]`, or the DTO's fields get stripped by the group
 * filter before the processor ever runs. Reflection-based so it fails
 * loudly the moment someone adds a new custom Post without the override,
 * without needing a full HTTP kernel round trip.
 */
final class BankStatementLineApiResourceTest extends TestCase
{
    public function testEveryCustomPostOperationOverridesDenormalizationContext(): void
    {
        $reflection = new \ReflectionClass(BankStatementLine::class);
        $apiResource = $reflection->getAttributes(ApiResource::class)[0]->newInstance();

        $customPostCount = 0;
        foreach ($apiResource->getOperations() as $operation) {
            if (!$operation instanceof Post || $operation->getUriTemplate() === null) {
                continue;
            }

            $customPostCount++;
            self::assertSame(
                [],
                $operation->getDenormalizationContext(),
                sprintf(
                    'Custom Post operation %s must override denormalizationContext to [].',
                    $operation->getUriTemplate(),
                ),
            );
        }

        self::assertSame(3, $customPostCount, 'Expected match/unmatch/ignore custom Post operations.');
    }

    public function testAutoMatchOperationOverridesDenormalizationContext(): void
    {
        $reflection = new \ReflectionClass(BankStatementImport::class);
        $apiResource = $reflection->getAttributes(ApiResource::class)[0]->newInstance();

        $customPostCount = 0;
        foreach ($apiResource->getOperations() as $operation) {
            if (!$operation instanceof Post || $operation->getUriTemplate() === null) {
                continue;
            }

            $customPostCount++;
            self::assertSame([], $operation->getDenormalizationContext());
        }

        self::assertSame(1, $customPostCount, 'Expected the auto-match custom Post operation.');
    }
}
