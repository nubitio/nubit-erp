<?php

declare(strict_types=1);

namespace App\AccountsPayable\Entity;

use App\Catalog\Entity\PaymentMethod;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One payment applied against a SupplierBill. Not exposed as its own
 * ApiResource — created only via SupplierBillPayProcessor
 * (POST /supplier-bills/{id}/pay), same reasoning as SalePayment on the
 * sales side: it's a line in the bill's payment history, not a standalone
 * resource clients create directly.
 */
#[ORM\Entity]
#[ORM\Table(name: 'supplier_payment')]
#[ORM\Index(name: 'idx_supplier_payment_bill', columns: ['supplier_bill_id'])]
#[ORM\UniqueConstraint(name: 'uniq_supplier_payment_idempotency_key', columns: ['idempotency_key'])]
class SupplierPayment
{
    #[Groups(['supplier_bill:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SupplierBill::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'supplier_bill_id', referencedColumnName: 'id', nullable: false)]
    private SupplierBill $supplierBill;

    #[Groups(['supplier_bill:read'])]
    #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
    #[ORM\JoinColumn(name: 'payment_method_id', referencedColumnName: 'id', nullable: false)]
    private PaymentMethod $paymentMethod;

    #[Groups(['supplier_bill:read'])]
    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[Groups(['supplier_bill:read'])]
    #[ORM\Column(name: 'paid_at', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $paidAt;

    #[Groups(['supplier_bill:read'])]
    #[ORM\Column(name: 'reference', length: 100, nullable: true)]
    private ?string $reference = null;

    #[Groups(['supplier_bill:read'])]
    #[ORM\Column(name: 'reversed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $reversedAt = null;

    /** Stable id of the request that created this payment, so a retry returns it instead of paying twice. */
    #[ORM\Column(name: 'idempotency_key', length: 64, nullable: true)]
    private ?string $idempotencyKey = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'usuario_id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupplierBill(): SupplierBill
    {
        return $this->supplierBill;
    }

    public function setSupplierBill(SupplierBill $supplierBill): static
    {
        $this->supplierBill = $supplierBill;

        return $this;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethod $paymentMethod): static
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

    public function getPaidAt(): DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
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

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getReversedAt(): ?DateTimeImmutable
    {
        return $this->reversedAt;
    }

    public function isReversed(): bool
    {
        return $this->reversedAt !== null;
    }

    public function setReversedAt(?DateTimeImmutable $reversedAt): static
    {
        $this->reversedAt = $reversedAt;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
