<?php

declare(strict_types=1);

namespace App\Tests\Billing\Entity;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentEvent;
use PHPUnit\Framework\TestCase;

final class TaxDocumentEventTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $doc = new TaxDocument();
        $event = new TaxDocumentEvent();

        $event->setTaxDocument($doc);
        $event->setEventType('sunat_send');
        $event->setStatus('accepted');
        $event->setCode('0');
        $event->setMessage('El Comprobante numero F001-1 ha sido aceptado');
        $event->setPayloadJson('{"name":"20123456789-01-F001-1"}');

        self::assertSame($doc, $event->getTaxDocument());
        self::assertSame('sunat_send', $event->getEventType());
        self::assertSame('accepted', $event->getStatus());
        self::assertSame('0', $event->getCode());
        self::assertSame('El Comprobante numero F001-1 ha sido aceptado', $event->getMessage());
        self::assertSame('{"name":"20123456789-01-F001-1"}', $event->getPayloadJson());
        self::assertNull($event->getId()); // not persisted
    }

    public function testDefaultsAreNull(): void
    {
        $event = new TaxDocumentEvent();

        self::assertNull($event->getId());
        self::assertNull($event->getStatus());
        self::assertNull($event->getCode());
        self::assertNull($event->getMessage());
        self::assertNull($event->getPayloadJson());
    }

    public function testCreatedAtIsSetOnConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $event = new TaxDocumentEvent();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $event->getCreatedAt());
        self::assertLessThanOrEqual($after, $event->getCreatedAt());
    }

    public function testFluentSettersReturnSelf(): void
    {
        $doc = new TaxDocument();
        $event = new TaxDocumentEvent();

        self::assertSame($event, $event->setTaxDocument($doc));
        self::assertSame($event, $event->setEventType('test'));
        self::assertSame($event, $event->setStatus('ok'));
        self::assertSame($event, $event->setCode('0'));
        self::assertSame($event, $event->setMessage('msg'));
        self::assertSame($event, $event->setPayloadJson('{}'));
    }
}
