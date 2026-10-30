<?php

declare(strict_types=1);

namespace App\Tests\Billing\Controller;

use App\Billing\Controller\TaxDocumentEventsController;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentEvent;
use App\Billing\Repository\TaxDocumentEventRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TaxDocumentEventsControllerTest extends TestCase
{
    public function testReturnsDocumentScopedEventsWithoutInternalPayloads(): void
    {
        $document = new TaxDocument();
        $event = new TaxDocumentEvent()
            ->setTaxDocument($document)
            ->setEventType('sunat_send')
            ->setCode('0')
            ->setMessage('Aceptado')
            ->setCreatedAt(new DateTimeImmutable('2026-10-29T12:00:00-05:00'))
            ->setPayloadJson('{"internal":"private"}');
        $repository = $this->createMock(TaxDocumentEventRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['taxDocument' => $document], ['createdAt' => 'DESC', 'id' => 'DESC'], 50)
            ->willReturn([$event]);
        $response = (new TaxDocumentEventsController($repository))($document);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('0', $data[0]['code']);
        self::assertSame('2026-10-29T12:00:00-05:00', $data[0]['createdAt']);
        self::assertArrayNotHasKey('payloadJson', $data[0]);
        self::assertStringNotContainsString('private', $response->getContent());
    }
}
