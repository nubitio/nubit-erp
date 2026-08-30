<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Integration\Entity\DocumentInputSummary;
use App\Integration\Entity\IntegrationDocument;
use App\Integration\Event\EventType;
use App\Integration\Event\IntegrationEvent;
use App\Integration\Event\IntegrationEventRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class IntegrationDocumentAndEventTest extends TestCase
{
    private function document(): IntegrationDocument
    {
        return new IntegrationDocument(
            'ext-1',
            'efact-20260830-abcd',
            str_repeat('0', 64),
            ['external_id' => 'ext-1'],
            new DocumentInputSummary('01', 'F001', 7),
        );
    }

    public function testStatusMachineOnlyMovesForward(): void
    {
        $doc = $this->document();
        self::assertSame(IntegrationDocument::STATUS_QUEUED, $doc->getStatus());
        self::assertFalse($doc->isTerminal());

        $doc->markSent();
        self::assertSame(IntegrationDocument::STATUS_SENT, $doc->getStatus());

        $doc->markAccepted('0', 'R-1');
        self::assertSame(IntegrationDocument::STATUS_ACCEPTED, $doc->getStatus());
        self::assertTrue($doc->isTerminal());
        self::assertSame('R-1', $doc->getCdrReference());

        // markSent after a terminal state is a no-op
        $doc->markSent();
        self::assertSame(IntegrationDocument::STATUS_ACCEPTED, $doc->getStatus());
    }

    public function testEventTypeCatalog(): void
    {
        self::assertSame(EventType::DocumentAccepted, EventType::forDocumentStatus('accepted'));
        self::assertSame(EventType::DocumentRejected, EventType::forDocumentStatus('rejected'));
        self::assertNull(EventType::forDocumentStatus('queued'));
        self::assertSame(['document.accepted', 'document.rejected', 'document.voided'], EventType::all());

        self::assertTrue(EventType::DocumentAccepted->matches('document.accepted'));
        self::assertTrue(EventType::DocumentAccepted->matches('document.*'));
        self::assertTrue(EventType::DocumentAccepted->matches('*'));
        self::assertFalse(EventType::DocumentAccepted->matches('document.rejected'));
    }

    public function testRecorderPersistsAnOutboxRowForATerminalDocument(): void
    {
        $doc = $this->document();
        $doc->markRejected('SUNAT rechazó', '2335');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });

        $event = new IntegrationEventRecorder($em)->recordTerminal($doc);

        self::assertInstanceOf(IntegrationEvent::class, $event);
        self::assertCount(1, $persisted);
        self::assertSame('document.rejected', $event->getType());
        self::assertSame('ext-1', $event->getResourceId());
        self::assertSame('2335', $event->getPayload()['sunat_status']);
        self::assertSame(IntegrationEvent::STATUS_PENDING, $event->getStatus());
    }

    public function testRecorderIgnoresNonTerminalDocuments(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        self::assertNull(new IntegrationEventRecorder($em)->recordTerminal($this->document()));
    }
}
