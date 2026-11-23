<?php

declare(strict_types=1);

namespace App\Tests\Sales\Entity;

use App\Sales\Entity\SaleRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class SaleRecordSerializationTest extends TestCase
{
    public function testSaleReadPayloadIncludesStatusWithoutExposingVoidAuditFields(): void
    {
        $sale = new SaleRecord()
            ->setStatus('VOIDED')
            ->recordVoid(42, new DateTimeImmutable('2026-11-23'), 'Duplicate sale');
        $serializer = new Serializer([
            new DateTimeNormalizer(),
            new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader())),
        ]);
        $payload = $serializer->normalize($sale, null, ['groups' => ['sale_record:read']]);

        self::assertIsArray($payload);
        self::assertArrayHasKey('status', $payload);
        self::assertSame('VOIDED', $payload['status']);
        self::assertArrayNotHasKey('voidedByUserId', $payload);
        self::assertArrayNotHasKey('voidedAt', $payload);
        self::assertArrayNotHasKey('voidReason', $payload);
    }
}
