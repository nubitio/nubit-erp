<?php

declare(strict_types=1);

namespace App\Pharmacy\Entity;

use App\Pharmacy\Repository\PharmacyBatchMovementRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only record of which pharmacy batch a sale drew down (NB-12), written
 * by the SaleConfirmed handler using FEFO order. Inventory's `stock_lots` stays
 * the source of truth for total quantity per lot; this table only says *which
 * batch* each sale consumed, for traceability and expiry auditing.
 */
#[ORM\Entity(repositoryClass: PharmacyBatchMovementRepository::class)]
#[ORM\Table(name: 'pharmacy_batch_movements')]
#[ORM\Index(name: 'idx_pharmacy_batch_movement_batch', columns: ['batch_id'])]
#[ORM\Index(name: 'idx_pharmacy_batch_movement_sale', columns: ['sale_id'])]
class PharmacyBatchMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $batchId;

    #[ORM\Column]
    private int $productId;

    #[ORM\Column(length: 255)]
    private string $saleId;

    #[ORM\Column(length: 32)]
    private string $quantity;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $occurredAt;

    public function __construct(
        int $batchId,
        int $productId,
        string $saleId,
        string $quantity,
        DateTimeImmutable $occurredAt,
    ) {
        $this->batchId = $batchId;
        $this->productId = $productId;
        $this->saleId = $saleId;
        $this->quantity = $quantity;
        $this->occurredAt = $occurredAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBatchId(): int
    {
        return $this->batchId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getSaleId(): string
    {
        return $this->saleId;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
