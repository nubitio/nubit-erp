<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use App\Sales\Repository\CustomerPaymentApplicationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a customer-account movement (a PAYMENT or ADVANCE_APPLIED entry) to one
 * sale it settles, and by how much. This is the piece that does not exist today:
 * `sale_payments` has a hard FK to a single sale, so there is no way to record
 * that one payment covered several sales, or that an advance was applied later.
 *
 * When the application also materialises an internal `sale_payments` row (#381),
 * `salePaymentId` points at it; for a pure advance application there may be none.
 */
#[ORM\Entity(repositoryClass: CustomerPaymentApplicationRepository::class)]
#[ORM\Table(name: 'customer_payment_applications')]
#[ORM\Index(name: 'idx_customer_payment_application_entry', columns: ['customer_account_entry_id'])]
#[ORM\Index(name: 'idx_customer_payment_application_sale', columns: ['sale_id'])]
class CustomerPaymentApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerAccountEntry::class)]
    #[ORM\JoinColumn(
        name: 'customer_account_entry_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'CASCADE',
    )]
    private CustomerAccountEntry $accountEntry;

    #[ORM\Column(name: 'sale_id', type: Types::STRING, length: 255)]
    private string $saleId;

    #[ORM\Column(name: 'sale_payment_id', type: Types::BIGINT, nullable: true)]
    private ?int $salePaymentId = null;

    #[ORM\Column(name: 'applied_amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $appliedAmount;

    #[ORM\Column(name: 'currency', type: Types::STRING, length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[ORM\Column(name: 'applied_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $appliedAt;

    public function __construct(
        CustomerAccountEntry $accountEntry,
        string $saleId,
        string $appliedAmount,
        string $currency,
        ?DateTimeImmutable $appliedAt = null,
        ?int $salePaymentId = null,
    ) {
        $this->accountEntry = $accountEntry;
        $this->saleId = $saleId;
        $this->appliedAmount = $appliedAmount;
        $this->currency = $currency;
        $this->appliedAt = $appliedAt ?? new DateTimeImmutable();
        $this->salePaymentId = $salePaymentId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountEntry(): CustomerAccountEntry
    {
        return $this->accountEntry;
    }

    public function getSaleId(): string
    {
        return $this->saleId;
    }

    public function getSalePaymentId(): ?int
    {
        return $this->salePaymentId;
    }

    public function setSalePaymentId(?int $salePaymentId): static
    {
        $this->salePaymentId = $salePaymentId;

        return $this;
    }

    public function getAppliedAmount(): string
    {
        return $this->appliedAmount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAppliedAt(): DateTimeImmutable
    {
        return $this->appliedAt;
    }
}
