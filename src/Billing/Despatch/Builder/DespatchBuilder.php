<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Builder;

use App\Billing\Despatch\Entity\DespatchGuide;
use App\Billing\Sunat\Domain\BillUtilsInterface;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle;
use Greenter\Model\Sale\Document;

/**
 * Construye el modelo Greenter {@see Despatch} (Guía de Remisión Remitente) a
 * partir de la entidad {@see DespatchGuide}, listo para serializar y enviar a
 * SUNAT a través de lycet.
 */
final readonly class DespatchBuilder
{
    /** Modalidad de transporte público (Catálogo 18). */
    private const MODE_PUBLIC = '01';

    public function __construct(
        private BillUtilsInterface $billUtils,
    ) {}

    /**
     * @param array{type: string, number: string}|null $relatedDocument Comprobante de venta que origina el traslado (tipo SUNAT, serie-número)
     */
    public function build(DespatchGuide $guide, ?array $relatedDocument = null): Despatch
    {
        $recipientAddress = new Address();
        $recipientAddress->setDireccion($guide->getDestinationAddress());

        $recipient = new Client();
        $recipient
            ->setTipoDoc($guide->getRecipientDocType())
            ->setNumDoc($guide->getRecipientDocNumber())
            ->setRznSocial($guide->getRecipientName())
            ->setAddress($recipientAddress);

        $despatch = new Despatch();
        $despatch
            ->setVersion('2022')
            ->setTipoDoc($guide->getDocumentType())
            ->setSerie($guide->getSeries())
            ->setCorrelativo($guide->getNumber() ?? '')
            ->setFechaEmision($guide->getIssueDate())
            ->setCompany($this->billUtils->getCompany())
            ->setDestinatario($recipient)
            ->setEnvio($this->buildShipment($guide))
            ->setDetails($this->buildDetails($guide));

        if ($relatedDocument !== null) {
            $related = new Document();
            $related->setTipoDoc($relatedDocument['type'])->setNroDoc($relatedDocument['number']);
            $despatch->setRelDoc($related);
        }

        return $despatch;
    }

    private function buildShipment(DespatchGuide $guide): Shipment
    {
        $origin = new Direction($guide->getOriginUbigeo(), $guide->getOriginAddress());
        $destination = new Direction($guide->getDestinationUbigeo(), $guide->getDestinationAddress());

        $shipment = new Shipment();
        $shipment
            ->setCodTraslado($guide->getTransferReason())
            ->setDesTraslado($guide->getTransferReasonDescription())
            ->setModTraslado($guide->getTransportMode())
            ->setFecTraslado($this->toDateTime($guide->getTransferDate()))
            ->setPesoTotal((float) $guide->getTotalWeight())
            ->setUndPesoTotal($guide->getWeightUnit())
            ->setNumBultos($guide->getPackageCount())
            ->setPartida($origin)
            ->setLlegada($destination);

        if ($guide->getTransportMode() === self::MODE_PUBLIC) {
            $shipment->setTransportista($this->buildCarrier($guide));
        } else {
            $vehicle = new Vehicle();
            $vehicle->setPlaca($guide->getVehiclePlate());
            $shipment->setVehiculo($vehicle);
            $shipment->setChoferes([$this->buildDriver($guide)]);
        }

        return $shipment;
    }

    private function buildCarrier(DespatchGuide $guide): Transportist
    {
        $carrier = new Transportist();
        $carrier
            ->setTipoDoc($guide->getCarrierDocType())
            ->setNumDoc($guide->getCarrierDocNumber())
            ->setRznSocial($guide->getCarrierName())
            ->setNroMtc($guide->getCarrierMtc());

        return $carrier;
    }

    private function buildDriver(DespatchGuide $guide): Driver
    {
        $driver = new Driver();
        $driver
            ->setTipo('Principal')
            ->setTipoDoc($guide->getDriverDocType())
            ->setNroDoc($guide->getDriverDocNumber())
            ->setLicencia($guide->getDriverLicense())
            ->setNombres($guide->getDriverNames())
            ->setApellidos($guide->getDriverLastNames());

        return $driver;
    }

    /**
     * @return list<DespatchDetail>
     */
    private function buildDetails(DespatchGuide $guide): array
    {
        $details = [];
        foreach ($guide->getLines() as $line) {
            $detail = new DespatchDetail();
            $detail
                ->setCodigo($line->getProductCode())
                ->setDescripcion($line->getDescription())
                ->setUnidad($line->getUnit())
                ->setCantidad((float) $line->getQuantity())
                ->setCodProdSunat($line->getSunatProductCode());
            $details[] = $detail;
        }

        return $details;
    }

    private function toDateTime(\DateTimeImmutable $date): \DateTime
    {
        return \DateTime::createFromImmutable($date);
    }
}
