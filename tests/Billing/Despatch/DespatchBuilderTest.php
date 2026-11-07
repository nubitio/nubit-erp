<?php

declare(strict_types=1);

namespace App\Tests\Billing\Despatch;

use App\Billing\Despatch\Builder\DespatchBuilder;
use App\Billing\Sunat\Domain\BillUtilsInterface;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\Shipment;
use PHPUnit\Framework\TestCase;

final class DespatchBuilderTest extends TestCase
{
    public function testPrivateTransportMapsVehicleDriverAndIdentity(): void
    {
        $despatch = $this->builder()->build(DespatchGuideFactory::privateTransport());
        $shipment = $this->shipment($despatch);

        self::assertSame('09', $despatch->getTipoDoc());
        self::assertSame('T001-15', $despatch->getSerie() . '-' . $despatch->getCorrelativo());
        self::assertSame('T001-15', substr($despatch->getName(), -7));
        self::assertSame('01', $shipment->getCodTraslado());
        self::assertSame('02', $shipment->getModTraslado());
        self::assertSame('ABC123', $shipment->getVehiculo()?->getPlaca());
        $drivers = $shipment->getChoferes() ?? [];
        self::assertCount(1, $drivers);
        self::assertSame('12345678', $drivers[0]->getNroDoc());
        self::assertNull($shipment->getTransportista());
        self::assertSame('150101', $shipment->getPartida()?->getUbigueo());
        self::assertSame('040101', $shipment->getLlegada()?->getUbigueo());
        self::assertSame(12.5, $shipment->getPesoTotal());
        self::assertSame('20123456789', $despatch->getDestinatario()?->getNumDoc());
        $details = $despatch->getDetails() ?? [];
        self::assertCount(1, $details);
        self::assertSame(10.0, $details[0]->getCantidad());
        self::assertNull($despatch->getRelDoc());
    }

    public function testPublicTransportMapsCarrierInsteadOfVehicle(): void
    {
        $shipment = $this->shipment($this->builder()->build(DespatchGuideFactory::publicTransport()));

        self::assertSame('01', $shipment->getModTraslado());
        $carrier = $shipment->getTransportista();
        self::assertNotNull($carrier);
        self::assertSame('20987654321', $carrier->getNumDoc());
        self::assertSame('MTC123', $carrier->getNroMtc());
        self::assertNull($shipment->getVehiculo());
        self::assertEmpty($shipment->getChoferes());
    }

    public function testMapsReasonDescriptionAndRelatedDocument(): void
    {
        $guide = DespatchGuideFactory::privateTransport()
            ->setTransferReason('13')
            ->setTransferReasonDescription('Muestras');

        $despatch = $this->builder()->build($guide, ['type' => '01', 'number' => 'F001-123']);

        self::assertSame('Muestras', $this->shipment($despatch)->getDesTraslado());
        $related = $despatch->getRelDoc();
        self::assertNotNull($related);
        self::assertSame('01', $related->getTipoDoc());
        self::assertSame('F001-123', $related->getNroDoc());
    }

    private function shipment(Despatch $despatch): Shipment
    {
        $shipment = $despatch->getEnvio();
        self::assertInstanceOf(Shipment::class, $shipment);

        return $shipment;
    }

    private function builder(): DespatchBuilder
    {
        $company = new Company();
        $company->setRuc('20000000001')->setRazonSocial('EMPRESA SAC');
        $billUtils = $this->createMock(BillUtilsInterface::class);
        $billUtils->method('getCompany')->willReturn($company);

        return new DespatchBuilder($billUtils);
    }
}
