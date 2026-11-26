<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Repository\CustomerAccountEntryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One append-only movement on a customer's current account (#367, epic #367).
 *
 * This sits *on top of* per-sale debt (`SaleRecord.balanceDue` + `sale_payments`),
 * it does not replace it. A CHARGE mirrors a credit sale; a PAYMENT / ADVANCE /
 * CREDIT_NOTE moves the customer balance the other way. Every
 * row also stores `runningBalance`, the customer's balance immediately after the
 * movement, so the statement (#366 / #384) reads it straight instead of
 * recomputing in PHP.
 *
 * Rows are never updated or deleted in normal operation — corrections are new
 * offsetting entries.
 *
 * `customerId` points at Contacts' `cliente` table; there is deliberately no DB
 * FK across the context boundary (Sales already reaches `cliente` read-only via
 * raw SQL — see StatementRepository). `saleId` is likewise a loose reference to
 * keep the #384 backfill and tests free of insert ordering constraints.
 */
#[ORM\Entity(repositoryClass: CustomerAccountEntryRepository::class)]
#[ORM\Table(name: 'customer_account_entries')]
#[ORM\Index(name: 'idx_customer_account_entry_customer', columns: ['customer_id', 'id'])]
#[ORM\Index(name: 'idx_customer_account_entry_sale', columns: ['sale_id'])]
class CustomerAccountEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'customer_id', type: Types::INTEGER)]
    private int $customerId;

    #[ORM\Column(name: 'entry_type', type: Types::STRING, length: 32, enumType: CustomerAccountEntryType::class)]
    private CustomerAccountEntryType $entryType;

    #[ORM\Column(name: 'occurred_on', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $occurredOn;

    #[ORM\Column(name: 'debit', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $debit = '0.00';

    #[ORM\Column(name: 'credit', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $credit = '0.00';

    #[ORM\Column(name: 'currency', type: Types::STRING, length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[ORM\Column(name: 'running_balance', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $runningBalance = '0.00';

    #[ORM\Column(name: 'sale_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $saleId = null;

    #[ORM\Column(name: 'source', type: Types::STRING, length: 40, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'source_id', type: Types::STRING, length: 64, nullable: true)]
    private ?string $sourceId = null;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'branch_id', type: Types::STRING, length: 36, nullable: true)]
    private ?string $branchId;

    public function __construct(
        int $customerId,
        CustomerAccountEntryType $entryType,
        DateTimeImmutable $occurredOn,
        string $debit,
        string $credit,
        string $currency,
        string $runningBalance,
        ?string $saleId = null,
        ?string $source = null,
        ?string $sourceId = null,
        ?string $description = null,
        ?string $branchId = null,
    ) {
        $this->customerId = $customerId;
        $this->entryType = $entryType;
        $this->occurredOn = $occurredOn;
        $this->debit = $debit;
        $this->credit = $credit;
        $this->currency = $currency;
        $this->runningBalance = $runningBalance;
        $this->saleId = $saleId;
        $this->source = $source;
        $this->sourceId = $sourceId;
        $this->description = $description;
        $this->branchId = $branchId;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getEntryType(): CustomerAccountEntryType
    {
        return $this->entryType;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getDebit(): string
    {
        return $this->debit;
    }

    public function getCredit(): string
    {
        return $this->credit;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getRunningBalance(): string
    {
        return $this->runningBalance;
    }

    public function getSaleId(): ?string
    {
        return $this->saleId;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
