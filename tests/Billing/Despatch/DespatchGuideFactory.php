<?php

declare(strict_types=1);

namespace App\Tests\Billing\Despatch;

use App\Billing\Despatch\Entity\DespatchGuide;
use App\Billing\Despatch\Entity\DespatchGuideItem;
use DateTimeImmutable;

final class DespatchGuideFactory
{
    /** A valid private-transport guide for reason 01 (venta). */
    public static function privateTransport(): DespatchGuide
    {
        $guide = new DespatchGuide();
        $guide
            ->setSeries('T001')
            ->setNumber('15')
            ->setIssueDate(new DateTimeImmutable('2026-11-06'))
            ->setTransferDate(new DateTimeImmutable('2026-11-06'))
            ->setTransferReason('01')
            ->setTransportMode('02')
            ->setTotalWeight('12.500')
            ->setWeightUnit('KGM')
            ->setPackageCount(2)
            ->setRecipientDocType('6')
            ->setRecipientDocNumber('20123456789')
            ->setRecipientName('CLIENTE SAC')
            ->setOriginUbigeo('150101')
            ->setOriginAddress('AV. ORIGEN 123')
            ->setDestinationUbigeo('040101')
            ->setDestinationAddress('CALLE DESTINO 456')
            ->setVehiclePlate('ABC123')
            ->setDriverDocType('1')
            ->setDriverDocNumber('12345678')
            ->setDriverNames('JUAN')
            ->setDriverLastNames('PEREZ')
            ->setDriverLicense('Q12345678');

        $line = new DespatchGuideItem();
        $line->setDescription('Cemento Sol 42.5kg')->setUnit('NIU')->setQuantity('10');
        $guide->addLine($line);

        return $guide;
    }

    public static function publicTransport(): DespatchGuide
    {
        return self::privateTransport()
            ->setTransportMode('01')
            ->setCarrierDocType('6')
            ->setCarrierDocNumber('20987654321')
            ->setCarrierName('TRANSPORTES SAC')
            ->setCarrierMtc('MTC123')
            ->setVehiclePlate(null)
            ->setDriverDocType(null)
            ->setDriverDocNumber(null)
            ->setDriverNames(null)
            ->setDriverLastNames(null)
            ->setDriverLicense(null);
    }
}
