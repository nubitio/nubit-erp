<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\State\PurchaseVoidProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Verifies the API metadata contract for Purchase DELETE:
 *   - DELETE operation wires PurchaseVoidProcessor
 *   - output: false → 204 No Content
 *
 * Behavioural tests for the processor are in PurchaseVoidProcessorTest.
 */
final class PurchaseSoftDeleteProcessorTest extends TestCase
{
    public function testPurchaseDeleteOperationUsesVoidProcessorAndNoOutput(): void
    {
        $resource = (new ReflectionClass(Purchase::class))->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            if (!$operation instanceof Delete) {
                continue;
            }

            self::assertSame(PurchaseVoidProcessor::class, $operation->getProcessor());
            self::assertFalse($operation->getOutput());

            return;
        }

        self::fail('Purchase ApiResource is missing its DELETE operation.');
    }

    public function testPurchaseHasNoPutOperation(): void
    {
        $resource = (new ReflectionClass(Purchase::class))->getAttributes(ApiResource::class)[0]->newInstance();

        foreach ($resource->getOperations() ?? [] as $operation) {
            self::assertNotInstanceOf(
                \ApiPlatform\Metadata\Put::class,
                $operation,
                'PUT must be removed — purchases are immutable after creation.',
            );
        }
    }
}
