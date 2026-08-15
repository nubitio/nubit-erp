<?php

declare(strict_types=1);

namespace App\BankReconciliation\Entity;

use App\AccountsPayable\Entity\SupplierPayment;
use App\CashRegister\Entity\CashMovement;
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

    #[ORM\ManyToOne(targetEntity: CashMovement::class)]
    #[ORM\JoinColumn(name: 'cash_movement_id', referencedColumnName: 'id', nullable: true)]
    private ?CashMovement $cashMovement = null;

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

    public function getCashMovement(): ?CashMovement
    {
        return $this->cashMovement;
    }

    public function setCashMovement(?CashMovement $cashMovement): static
    {
        $this->cashMovement = $cashMovement;

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
