<?php

declare(strict_types=1);

namespace App\BankReconciliation\Entity;

use App\AccountsPayable\Entity\SupplierPayment;
use App\Identity\Entity\User;
use App\Sales\Entity\SalePayment;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Links a BankStatementLine to exactly one of SupplierPayment, SalePayment,
 * or CashMovement. Not exposed as its own ApiResource — created only via
 * BankStatementLineMatchProcessor, mirroring how SupplierPayment is a
 * log-only child of SupplierBill. This entity is the single source of
 * truth for "is this movement reconciled" — the three source entities are
 * never modified.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bank_reconciliation_match')]
#[ORM\Index(name: 'idx_brm_supplier_payment', columns: ['supplier_payment_id'])]
#[ORM\Index(name: 'idx_brm_sale_payment', columns: ['sale_payment_id'])]
#[ORM\Index(name: 'idx_brm_cash_movement', columns: ['cash_movement_id'])]
class BankReconciliationMatch
{
    public const string AUTO = 'AUTO';

    public const string MANUAL = 'MANUAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: BankStatementLine::class)]
    #[ORM\JoinColumn(name: 'bank_statement_line_id', referencedColumnName: 'id', nullable: false, unique: true)]
    private BankStatementLine $bankStatementLine;

    #[ORM\ManyToOne(targetEntity: SupplierPayment::class)]
    #[ORM\JoinColumn(name: 'supplier_payment_id', referencedColumnName: 'id', nullable: true)]
    private ?SupplierPayment $supplierPayment = null;

    #[ORM\ManyToOne(targetEntity: SalePayment::class)]
    #[ORM\JoinColumn(name: 'sale_payment_id', referencedColumnName: 'id', nullable: true)]
    private ?SalePayment $salePayment = null;

    // CashMovement is a DBAL-backed API model, not a Doctrine entity. Keep
    // its v2 cash_movements identifier as a scalar without an ORM relation.
    #[ORM\Column(name: 'cash_movement_id', type: Types::INTEGER, nullable: true)]
    private ?int $cashMovementId = null;

    // The Treasury movement (ADR-021) this line was reconciled against; a uuid kept as a scalar, since the
    // movement ledger belongs to another context.
    #[ORM\Column(name: 'treasury_movement_id', length: 36, nullable: true)]
    private ?string $treasuryMovementId = null;

    #[ORM\Column(name: 'match_type', length: 20)]
    private string $matchType;

    #[ORM\Column(name: 'matched_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $matchedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'matched_by', referencedColumnName: 'usuario_id', nullable: false)]
    private User $matchedBy;

    public function __construct()
    {
        $this->matchedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBankStatementLine(): BankStatementLine
    {
        return $this->bankStatementLine;
    }

    public function setBankStatementLine(BankStatementLine $bankStatementLine): static
    {
        $this->bankStatementLine = $bankStatementLine;

        return $this;
    }

    public function getSupplierPayment(): ?SupplierPayment
    {
        return $this->supplierPayment;
    }

    public function setSupplierPayment(?SupplierPayment $supplierPayment): static
    {
        $this->supplierPayment = $supplierPayment;

        return $this;
    }

    public function getSalePayment(): ?SalePayment
    {
        return $this->salePayment;
    }

    public function setSalePayment(?SalePayment $salePayment): static
    {
        $this->salePayment = $salePayment;

        return $this;
    }

    public function getTreasuryMovementId(): ?string
    {
        return $this->treasuryMovementId;
    }

    public function setTreasuryMovementId(?string $treasuryMovementId): static
    {
        $this->treasuryMovementId = $treasuryMovementId;

        return $this;
    }

    public function getCashMovementId(): ?int
    {
        return $this->cashMovementId;
    }

    public function setCashMovementId(?int $cashMovementId): static
    {
        $this->cashMovementId = $cashMovementId;

        return $this;
    }

    public function getMatchType(): string
    {
        return $this->matchType;
    }

    public function setMatchType(string $matchType): static
    {
        $this->matchType = $matchType;

        return $this;
    }

    public function getMatchedAt(): DateTimeImmutable
    {
        return $this->matchedAt;
    }

    public function getMatchedBy(): User
    {
        return $this->matchedBy;
    }

    public function setMatchedBy(User $matchedBy): static
    {
        $this->matchedBy = $matchedBy;

        return $this;
    }
}
