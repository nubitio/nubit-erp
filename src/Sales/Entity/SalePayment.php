<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use App\Catalog\Entity\PaymentMethod;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * ORM entity mapping the sale_payments table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sale_payments')]
#[ORM\HasLifecycleCallbacks]
class SalePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SaleRecord::class, inversedBy: 'salePayments')]
    #[ORM\JoinColumn(name: 'sale_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SaleRecord $saleRecord;

    #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', referencedColumnName: 'id', nullable: true)]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(name: 'currency', type: 'string', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[ORM\Column(name: 'tendered_amount', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $tenderedAmount = null;

    #[ORM\Column(name: 'change_amount', type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $changeAmount = null;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $paidAt = null;

    #[ORM\Column(name: 'reference', type: 'string', length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'sync_status', type: 'string', length: 20, options: ['default' => 'SYNCED'])]
    private string $syncStatus = 'SYNCED';

    #[ORM\Column(name: 'local_uuid', type: 'string', length: 36, nullable: true)]
    private ?string $localUuid = null;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 36, nullable: true)]
    private ?string $idempotencyKey = null;

    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $syncedAt = null;

    #[ORM\Column(name: 'device_id', type: 'string', length: 64, nullable: true)]
    private ?string $deviceId = null;

    #[ORM\Column(name: 'version_no', type: 'integer', options: ['default' => 1])]
    private int $versionNo = 1;

    #[ORM\Column(name: 'last_modified_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastModifiedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleRecord(): SaleRecord
    {
        return $this->saleRecord;
    }

    public function setSaleRecord(SaleRecord $saleRecord): static
    {
        $this->saleRecord = $saleRecord;

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getTenderedAmount(): ?string
    {
        return $this->tenderedAmount;
    }

    public function setTenderedAmount(?string $tenderedAmount): static
    {
        $this->tenderedAmount = $tenderedAmount;

        return $this;
    }

    public function getChangeAmount(): ?string
    {
        return $this->changeAmount;
    }

    public function setChangeAmount(?string $changeAmount): static
    {
        $this->changeAmount = $changeAmount;

        return $this;
    }

    public function getPaidAt(): ?DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $normalized = trim((string) $reference);
        $this->reference = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getLocalUuid(): ?string
    {
        return $this->localUuid;
    }

    public function setLocalUuid(?string $localUuid): static
    {
        $this->localUuid = $localUuid;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getSyncedAt(): ?DateTimeInterface
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?DateTimeInterface $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): static
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getVersionNo(): int
    {
        return $this->versionNo;
    }

    public function setVersionNo(int $versionNo): static
    {
        $this->versionNo = $versionNo;

        return $this;
    }

    public function getLastModifiedAt(): ?DateTimeInterface
    {
        return $this->lastModifiedAt;
    }

    public function setLastModifiedAt(?DateTimeInterface $lastModifiedAt): static
    {
        $this->lastModifiedAt = $lastModifiedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setTimestampsOnPersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
        if ($this->lastModifiedAt === null) {
            $this->lastModifiedAt = new \DateTime();
        }
    }
}
