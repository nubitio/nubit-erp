<?php

declare(strict_types=1);

namespace App\Tests\Sales\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Sales\Entity\SaleQuote;
use PHPUnit\Framework\TestCase;

/**
 * Guards against the exact bug class already hit on /supplier-bills/{id}/pay,
 * /sales/{id}/collect, and every custom Post in Fase 1's bank reconciliation
 * module: a custom Post operation on a resource whose class-level
 * denormalizationContext uses groups MUST override it to `[]`, or the DTO's
 * fields get stripped by the group filter before the processor ever runs.
 */
final class SaleQuoteApiResourceTest extends TestCase
{
    public function testEveryCustomPostOperationOverridesDenormalizationContext(): void
    {
        $reflection = new \ReflectionClass(SaleQuote::class);
        $apiResource = $reflection->getAttributes(ApiResource::class)[0]->newInstance();

        $customPostCount = 0;
        $operations = $apiResource->getOperations() ?? throw new \LogicException('Missing resource operations.');
        foreach ($operations as $operation) {
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

        self::assertSame(2, $customPostCount, 'Expected convert/send custom Post operations.');
    }
}
