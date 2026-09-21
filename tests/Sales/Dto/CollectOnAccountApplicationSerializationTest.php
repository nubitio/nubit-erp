<?php

declare(strict_types=1);

namespace App\Tests\Sales\Dto;

use App\Sales\Dto\CollectOnAccountApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class CollectOnAccountApplicationSerializationTest extends TestCase
{
    public function testAdvanceResponseIncludesApplicationFields(): void
    {
        $metadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizer = new ObjectNormalizer($metadataFactory);

        $payload = $normalizer->normalize(
            new CollectOnAccountApplication('sale-1', 'F001-10', '17.00', '0.00'),
            null,
            ['groups' => ['customer_advance:read']],
        );

        self::assertSame(
            [
                'saleId' => 'sale-1',
                'document' => 'F001-10',
                'applied' => '17.00',
                'balanceDueAfter' => '0.00',
            ],
            $payload,
        );
    }
}
