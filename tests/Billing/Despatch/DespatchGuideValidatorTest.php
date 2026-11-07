<?php

declare(strict_types=1);

namespace App\Tests\Billing\Despatch;

use App\Billing\Despatch\Domain\DespatchGuideValidator;
use App\Billing\Despatch\Entity\DespatchGuideItem;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DespatchGuideValidatorTest extends TestCase
{
    private DespatchGuideValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DespatchGuideValidator();
    }

    public function testValidPrivateTransportGuidePasses(): void
    {
        self::assertSame([], $this->validator->validate(DespatchGuideFactory::privateTransport()));
    }

    public function testValidPublicTransportGuidePasses(): void
    {
        self::assertSame([], $this->validator->validate(DespatchGuideFactory::publicTransport()));
    }

    public function testRejectsSeriesNotStartingWithT(): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setSeries('F001');

        self::assertStringContainsString('serie', $this->validator->validate($guide)[0]);
    }

    public function testRejectsTransferBeforeIssueDate(): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setTransferDate(new DateTimeImmutable('2026-11-01'));

        self::assertStringContainsString('fecha de inicio', $this->validator->validate($guide)[0]);
    }

    public function testOtherReasonRequiresDescription(): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setTransferReason('13');
        self::assertStringContainsString('Otros', $this->validator->validate($guide)[0]);

        $guide->setTransferReasonDescription('Muestras sin valor comercial');
        self::assertSame([], $this->validator->validate($guide));
    }

    public function testRejectsUnknownTransferReason(): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setTransferReason('99');

        self::assertStringContainsString('motivo', $this->validator->validate($guide)[0]);
    }

    public function testPrivateTransportRequiresVehicleAndDriver(): void
    {
        $guide = DespatchGuideFactory::privateTransport()
            ->setVehiclePlate(null)
            ->setDriverDocNumber('123')
            ->setDriverLicense(null);

        $errors = implode(' | ', $this->validator->validate($guide));

        self::assertStringContainsString('placa', $errors);
        self::assertStringContainsString('DNI del conductor', $errors);
        self::assertStringContainsString('licencia', $errors);
    }

    public function testPublicTransportRequiresCarrier(): void
    {
        $guide = DespatchGuideFactory::publicTransport()->setCarrierDocNumber('123')->setCarrierName('');

        $errors = implode(' | ', $this->validator->validate($guide));

        self::assertStringContainsString('RUC del transportista', $errors);
        self::assertStringContainsString('razón social del transportista', $errors);
    }

    public function testRejectsBadRecipientAndUbigeos(): void
    {
        $guide = DespatchGuideFactory::privateTransport()
            ->setRecipientDocNumber('123')
            ->setOriginUbigeo('15')
            ->setDestinationUbigeo('ABCDEF');

        $errors = implode(' | ', $this->validator->validate($guide));

        self::assertStringContainsString('RUC del destinatario', $errors);
        self::assertStringContainsString('punto de partida debe tener 6', $errors);
        self::assertStringContainsString('punto de llegada debe tener 6', $errors);
    }

    public function testRejectsZeroWeightAndNonPositiveQuantity(): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setTotalWeight('0.000');
        $line = $guide->getLines()->first();
        self::assertInstanceOf(DespatchGuideItem::class, $line);
        $line->setQuantity('0');

        $errors = implode(' | ', $this->validator->validate($guide));

        self::assertStringContainsString('peso total', $errors);
        self::assertStringContainsString('ítem 1', $errors);
    }

    public function testRejectsInvalidTransportMode(): void
    {
        $guide = DespatchGuideFactory::privateTransport()->setTransportMode('09');

        self::assertStringContainsString('modalidad', $this->validator->validate($guide)[0]);
    }
}
