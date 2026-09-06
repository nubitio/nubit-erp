<?php

declare(strict_types=1);

namespace App\Pharmacy\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Pharmacy\Repository\PharmacyBatchRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Batch/lot metadata for a pharmacy product (NB-10): batch number, expiry,
 * manufacturer. Quantity-on-hand is NOT here — that stays in Inventory's
 * `stock_lots`; this row carries only the facts FEFO and expiry blocking need.
 */
#[ORM\Entity(repositoryClass: PharmacyBatchRepository::class)]
#[ORM\Table(name: 'pharmacy_batches')]
#[ORM\UniqueConstraint(name: 'uniq_pharmacy_batch', columns: ['product_id', 'batch_number'])]
#[ORM\Index(name: 'idx_pharmacy_batch_expiry', columns: ['expiration_date'])]
#[ApiResource(
    shortName: 'PharmacyBatch',
    operations: [
        new GetCollection(uriTemplate: '/pharmacy/batches'),
        new Get(uriTemplate: '/pharmacy/batches/{id}'),
    ],
    normalizationContext: ['groups' => ['pharmacy_batch:read']],
    filters: [DataGridFilter::class],
)]
class PharmacyBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pharmacy_batch:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['pharmacy_batch:read'])]
    private int $productId;

    #[ORM\Column(length: 64)]
    #[Groups(['pharmacy_batch:read'])]
    private string $batchNumber;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['pharmacy_batch:read'])]
    private DateTimeImmutable $expirationDate;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacy_batch:read'])]
    private ?string $manufacturer = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(int $productId, string $batchNumber, DateTimeImmutable $expirationDate)
    {
        $this->productId = $productId;
        $this->batchNumber = $batchNumber;
        $this->expirationDate = $expirationDate;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getBatchNumber(): string
    {
        return $this->batchNumber;
    }

    public function getExpirationDate(): DateTimeImmutable
    {
        return $this->expirationDate;
    }

    public function isExpiredOn(DateTimeImmutable $reference): bool
    {
        return $this->expirationDate < $reference->setTime(0, 0);
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
