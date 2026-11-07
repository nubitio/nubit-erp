<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Billing\Despatch\Controller\SendDespatchGuide;
use App\Billing\Despatch\Repository\DespatchGuideRepository;
use App\Billing\Despatch\State\DespatchGuideProcessor;
use App\Organization\Entity\Branch;
use App\Sales\Entity\SaleRecord;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Guía de Remisión Electrónica — Remitente (comprobante tipo 09).
 *
 * Modela el traslado de bienes que se envía a SUNAT a través de lycet. La
 * estructura cubre los dos modos de traslado (público/privado) y los datos del
 * destinatario, punto de partida y llegada exigidos por SUNAT.
 *
 * El envío es asíncrono: el Post persiste la guía y la operación `/send`
 * encola {@see \App\Billing\Despatch\Message\SendDespatchMessage}.
 */
#[ORM\Entity(repositoryClass: DespatchGuideRepository::class)]
#[ORM\Table(name: 'despatch_guide')]
#[ApiResource(
    shortName: 'DespatchGuide',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')", processor: DespatchGuideProcessor::class),
        new Post(
            uriTemplate: '/despatch_guides/{id}/send',
            uriVariables: ['id' => new Link(fromClass: DespatchGuide::class, identifiers: ['id'])],
            controller: SendDespatchGuide::class,
            read: false,
            deserialize: false,
            security: "is_granted('ROLE_ADMIN')",
            name: 'despatch_guide_send',
        ),
    ],
    normalizationContext: ['groups' => ['despatch:read']],
    denormalizationContext: ['groups' => ['despatch:write']],
    filters: [DataGridFilter::class],
    mercure: ['private' => true, 'data' => '{}'],
)]
class DespatchGuide
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['despatch:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Branch::class)]
    #[ORM\JoinColumn(name: 'branch_id', referencedColumnName: 'local_id', nullable: true)]
    private ?Branch $branch = null;

    /** Venta de origen (opcional, cuando el traslado es por venta). */
    #[ORM\ManyToOne(targetEntity: SaleRecord::class)]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['despatch:write'])]
    private ?SaleRecord $saleRecord = null;

    #[ORM\Column(name: 'document_type', type: Types::STRING, length: 2, options: ['default' => '09'])]
    #[Groups(['despatch:read'])]
    private string $documentType = '09';

    #[ORM\Column(name: 'series', type: Types::STRING, length: 10)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $series = '';

    #[ORM\Column(name: 'number', type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['despatch:read'])]
    private ?string $number = null;

    #[ORM\Column(name: 'issue_date', type: Types::DATE_IMMUTABLE)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private \DateTimeImmutable $issueDate;

    /** Fecha de inicio del traslado. */
    #[ORM\Column(name: 'transfer_date', type: Types::DATE_IMMUTABLE)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private \DateTimeImmutable $transferDate;

    /** Motivo de traslado — SUNAT Catálogo 20 (01 venta, 04 traslado entre establecimientos, …). */
    #[ORM\Column(name: 'transfer_reason', type: Types::STRING, length: 2)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $transferReason = '01';

    #[ORM\Column(name: 'transfer_reason_description', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $transferReasonDescription = null;

    /** Modalidad de traslado — SUNAT Catálogo 18 (01 transporte público, 02 transporte privado). */
    #[ORM\Column(name: 'transport_mode', type: Types::STRING, length: 2)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $transportMode = '02';

    #[ORM\Column(name: 'total_weight', type: Types::DECIMAL, precision: 12, scale: 3, options: ['default' => '0.000'])]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $totalWeight = '0.000';

    #[ORM\Column(name: 'weight_unit', type: Types::STRING, length: 5, options: ['default' => 'KGM'])]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $weightUnit = 'KGM';

    #[ORM\Column(name: 'package_count', type: Types::INTEGER, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?int $packageCount = null;

    // ── Destinatario ──────────────────────────────────────────────────────
    #[ORM\Column(name: 'recipient_doc_type', type: Types::STRING, length: 2, options: ['default' => '6'])]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $recipientDocType = '6';

    #[ORM\Column(name: 'recipient_doc_number', type: Types::STRING, length: 15)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $recipientDocNumber = '';

    #[ORM\Column(name: 'recipient_name', type: Types::STRING, length: 255)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $recipientName = '';

    // ── Punto de partida ──────────────────────────────────────────────────
    #[ORM\Column(name: 'origin_ubigeo', type: Types::STRING, length: 6)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $originUbigeo = '';

    #[ORM\Column(name: 'origin_address', type: Types::STRING, length: 255)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $originAddress = '';

    // ── Punto de llegada ──────────────────────────────────────────────────
    #[ORM\Column(name: 'destination_ubigeo', type: Types::STRING, length: 6)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $destinationUbigeo = '';

    #[ORM\Column(name: 'destination_address', type: Types::STRING, length: 255)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private string $destinationAddress = '';

    // ── Transportista (modalidad 01 — transporte público) ─────────────────
    #[ORM\Column(name: 'carrier_doc_type', type: Types::STRING, length: 2, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $carrierDocType = null;

    #[ORM\Column(name: 'carrier_doc_number', type: Types::STRING, length: 15, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $carrierDocNumber = null;

    #[ORM\Column(name: 'carrier_name', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $carrierName = null;

    #[ORM\Column(name: 'carrier_mtc', type: Types::STRING, length: 30, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $carrierMtc = null;

    // ── Vehículo y conductor (modalidad 02 — transporte privado) ──────────
    #[ORM\Column(name: 'vehicle_plate', type: Types::STRING, length: 10, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $vehiclePlate = null;

    #[ORM\Column(name: 'driver_doc_type', type: Types::STRING, length: 2, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $driverDocType = null;

    #[ORM\Column(name: 'driver_doc_number', type: Types::STRING, length: 15, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $driverDocNumber = null;

    #[ORM\Column(name: 'driver_names', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $driverNames = null;

    #[ORM\Column(name: 'driver_last_names', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $driverLastNames = null;

    #[ORM\Column(name: 'driver_license', type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['despatch:read', 'despatch:write'])]
    private ?string $driverLicense = null;

    // ── Estado SUNAT ──────────────────────────────────────────────────────
    #[ORM\Column(name: 'sunat_status', type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['despatch:read'])]
    private ?string $sunatStatus = null;

    #[ORM\Column(name: 'sunat_message', type: Types::TEXT, nullable: true)]
    #[Groups(['despatch:read'])]
    private ?string $sunatMessage = null;

    #[ORM\Column(name: 'sunat_ticket', type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['despatch:read'])]
    private ?string $sunatTicket = null;

    #[ORM\Column(name: 'cdr_path', type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['despatch:read'])]
    private ?string $cdrPath = null;

    /**
     * @var Collection<int, DespatchGuideItem>
     */
    #[ORM\OneToMany(
        targetEntity: DespatchGuideItem::class,
        mappedBy: 'despatchGuide',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[Groups(['despatch:read', 'despatch:write'])]
    private Collection $lines;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['despatch:read'])]
    private DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->issueDate = new \DateTimeImmutable();
        $this->transferDate = new \DateTimeImmutable();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    public function getSaleRecord(): ?SaleRecord
    {
        return $this->saleRecord;
    }

    public function setSaleRecord(?SaleRecord $saleRecord): self
    {
        $this->saleRecord = $saleRecord;

        return $this;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): self
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getTransferDate(): \DateTimeImmutable
    {
        return $this->transferDate;
    }

    public function setTransferDate(\DateTimeImmutable $transferDate): self
    {
        $this->transferDate = $transferDate;

        return $this;
    }

    public function getTransferReason(): string
    {
        return $this->transferReason;
    }

    public function setTransferReason(string $transferReason): self
    {
        $this->transferReason = $transferReason;

        return $this;
    }

    public function getTransferReasonDescription(): ?string
    {
        return $this->transferReasonDescription;
    }

    public function setTransferReasonDescription(?string $transferReasonDescription): self
    {
        $this->transferReasonDescription = $transferReasonDescription;

        return $this;
    }

    public function getTransportMode(): string
    {
        return $this->transportMode;
    }

    public function setTransportMode(string $transportMode): self
    {
        $this->transportMode = $transportMode;

        return $this;
    }

    public function getTotalWeight(): string
    {
        return $this->totalWeight;
    }

    public function setTotalWeight(string $totalWeight): self
    {
        $this->totalWeight = $totalWeight;

        return $this;
    }

    public function getWeightUnit(): string
    {
        return $this->weightUnit;
    }

    public function setWeightUnit(string $weightUnit): self
    {
        $this->weightUnit = $weightUnit;

        return $this;
    }

    public function getPackageCount(): ?int
    {
        return $this->packageCount;
    }

    public function setPackageCount(?int $packageCount): self
    {
        $this->packageCount = $packageCount;

        return $this;
    }

    public function getRecipientDocType(): string
    {
        return $this->recipientDocType;
    }

    public function setRecipientDocType(string $recipientDocType): self
    {
        $this->recipientDocType = $recipientDocType;

        return $this;
    }

    public function getRecipientDocNumber(): string
    {
        return $this->recipientDocNumber;
    }

    public function setRecipientDocNumber(string $recipientDocNumber): self
    {
        $this->recipientDocNumber = $recipientDocNumber;

        return $this;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): self
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getOriginUbigeo(): string
    {
        return $this->originUbigeo;
    }

    public function setOriginUbigeo(string $originUbigeo): self
    {
        $this->originUbigeo = $originUbigeo;

        return $this;
    }

    public function getOriginAddress(): string
    {
        return $this->originAddress;
    }

    public function setOriginAddress(string $originAddress): self
    {
        $this->originAddress = $originAddress;

        return $this;
    }

    public function getDestinationUbigeo(): string
    {
        return $this->destinationUbigeo;
    }

    public function setDestinationUbigeo(string $destinationUbigeo): self
    {
        $this->destinationUbigeo = $destinationUbigeo;

        return $this;
    }

    public function getDestinationAddress(): string
    {
        return $this->destinationAddress;
    }

    public function setDestinationAddress(string $destinationAddress): self
    {
        $this->destinationAddress = $destinationAddress;

        return $this;
    }

    public function getCarrierDocType(): ?string
    {
        return $this->carrierDocType;
    }

    public function setCarrierDocType(?string $carrierDocType): self
    {
        $this->carrierDocType = $carrierDocType;

        return $this;
    }

    public function getCarrierDocNumber(): ?string
    {
        return $this->carrierDocNumber;
    }

    public function setCarrierDocNumber(?string $carrierDocNumber): self
    {
        $this->carrierDocNumber = $carrierDocNumber;

        return $this;
    }

    public function getCarrierName(): ?string
    {
        return $this->carrierName;
    }

    public function setCarrierName(?string $carrierName): self
    {
        $this->carrierName = $carrierName;

        return $this;
    }

    public function getCarrierMtc(): ?string
    {
        return $this->carrierMtc;
    }

    public function setCarrierMtc(?string $carrierMtc): self
    {
        $this->carrierMtc = $carrierMtc;

        return $this;
    }

    public function getVehiclePlate(): ?string
    {
        return $this->vehiclePlate;
    }

    public function setVehiclePlate(?string $vehiclePlate): self
    {
        $this->vehiclePlate = $vehiclePlate;

        return $this;
    }

    public function getDriverDocType(): ?string
    {
        return $this->driverDocType;
    }

    public function setDriverDocType(?string $driverDocType): self
    {
        $this->driverDocType = $driverDocType;

        return $this;
    }

    public function getDriverDocNumber(): ?string
    {
        return $this->driverDocNumber;
    }

    public function setDriverDocNumber(?string $driverDocNumber): self
    {
        $this->driverDocNumber = $driverDocNumber;

        return $this;
    }

    public function getDriverNames(): ?string
    {
        return $this->driverNames;
    }

    public function setDriverNames(?string $driverNames): self
    {
        $this->driverNames = $driverNames;

        return $this;
    }

    public function getDriverLastNames(): ?string
    {
        return $this->driverLastNames;
    }

    public function setDriverLastNames(?string $driverLastNames): self
    {
        $this->driverLastNames = $driverLastNames;

        return $this;
    }

    public function getDriverLicense(): ?string
    {
        return $this->driverLicense;
    }

    public function setDriverLicense(?string $driverLicense): self
    {
        $this->driverLicense = $driverLicense;

        return $this;
    }

    public function getSunatStatus(): ?string
    {
        return $this->sunatStatus;
    }

    public function setSunatStatus(?string $sunatStatus): self
    {
        $this->sunatStatus = $sunatStatus;

        return $this;
    }

    public function getSunatMessage(): ?string
    {
        return $this->sunatMessage;
    }

    public function setSunatMessage(?string $sunatMessage): self
    {
        $this->sunatMessage = $sunatMessage;

        return $this;
    }

    public function getSunatTicket(): ?string
    {
        return $this->sunatTicket;
    }

    public function setSunatTicket(?string $sunatTicket): self
    {
        $this->sunatTicket = $sunatTicket;

        return $this;
    }

    public function getCdrPath(): ?string
    {
        return $this->cdrPath;
    }

    public function setCdrPath(?string $cdrPath): self
    {
        $this->cdrPath = $cdrPath;

        return $this;
    }

    /**
     * @return Collection<int, DespatchGuideItem>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(DespatchGuideItem $line): self
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setDespatchGuide($this);
        }

        return $this;
    }

    public function removeLine(DespatchGuideItem $line): self
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[Groups(['despatch:read'])]
    public function getFullNumber(): string
    {
        return sprintf('%s-%s', $this->series, $this->number ?? '');
    }

    public function isProcessed(): bool
    {
        return $this->sunatStatus !== null;
    }

    /**
     * SUNAT accepted the guide: CDR code 0 (accepted) or 4000+ (accepted with
     * observations). Codes 0100–3999 are exceptions/rejections and leave the
     * guide unregistered, so it may be sent again.
     */
    public function isAccepted(): bool
    {
        if ($this->sunatStatus === null || !ctype_digit($this->sunatStatus)) {
            return false;
        }

        $code = (int) $this->sunatStatus;

        return $code === 0 || $code >= 4000;
    }
}
