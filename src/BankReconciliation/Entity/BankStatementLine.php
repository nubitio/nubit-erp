<?php

declare(strict_types=1);

namespace App\BankReconciliation\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\BankReconciliation\Dto\BankStatementLineIgnoreDto;
use App\BankReconciliation\Dto\BankStatementLineMatchDto;
use App\BankReconciliation\Dto\BankStatementLineUnmatchDto;
use App\BankReconciliation\Repository\BankStatementLineRepository;
use App\BankReconciliation\State\BankStatementLineIgnoreProcessor;
use App\BankReconciliation\State\BankStatementLineMatchProcessor;
use App\BankReconciliation\State\BankStatementLineSuggestionsProvider;
use App\BankReconciliation\State\BankStatementLineUnmatchProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One line from an imported bank statement. Lines are only ever created by
 * the import pipeline (never a plain Post) and mutated only through the
 * match/unmatch/ignore actions below.
 */
#[ORM\Entity(repositoryClass: BankStatementLineRepository::class)]
#[ORM\Table(name: 'bank_statement_line')]
#[ORM\Index(name: 'idx_bank_statement_line_account_date', columns: ['bank_account_id', 'transaction_date'])]
#[ORM\Index(name: 'idx_bank_statement_line_status', columns: ['status'])]
#[ORM\Index(name: 'idx_bank_statement_line_batch', columns: ['import_batch_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            uriTemplate: '/bank-statement-lines/{id}/match',
            // BankStatementLineMatchDto has no serialization groups of its
            // own, so this must override the class-level
            // denormalizationContext below — otherwise the 'bank_statement_
            // line:write' group filter strips every property before the DTO
            // constructor runs (same bug class as SupplierBill's /pay op).
            denormalizationContext: [],
            input: BankStatementLineMatchDto::class,
            output: BankStatementLine::class,
            processor: BankStatementLineMatchProcessor::class,
        ),
        new Post(
            uriTemplate: '/bank-statement-lines/{id}/unmatch',
            denormalizationContext: [],
            input: BankStatementLineUnmatchDto::class,
            output: BankStatementLine::class,
            processor: BankStatementLineUnmatchProcessor::class,
        ),
        new Post(
            uriTemplate: '/bank-statement-lines/{id}/ignore',
            denormalizationContext: [],
            input: BankStatementLineIgnoreDto::class,
            output: BankStatementLine::class,
            processor: BankStatementLineIgnoreProcessor::class,
        ),
        new Get(
            uriTemplate: '/bank-statement-lines/{id}/suggestions',
            normalizationContext: ['groups' => ['bank_reconciliation_suggestion:read']],
            provider: BankStatementLineSuggestionsProvider::class,
            paginationEnabled: false,
        ),
    ],
    normalizationContext: ['groups' => ['bank_statement_line:read']],
    denormalizationContext: ['groups' => ['bank_statement_line:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class BankStatementLine
{
    public const string UNMATCHED = 'UNMATCHED';

    public const string MATCHED = 'MATCHED';

    public const string IGNORED = 'IGNORED';

    #[Groups(['bank_statement_line:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[Groups(['bank_statement_line:read'])]
    #[ApiProperty(description: 'bank_statement_line.bank_account.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: BankAccount::class)]
    #[ORM\JoinColumn(name: 'bank_account_id', referencedColumnName: 'id', nullable: false)]
    private BankAccount $bankAccount;

    #[Groups(['bank_statement_line:read'])]
    #[ApiProperty(description: 'bank_statement_line.import_batch.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: BankStatementImport::class)]
    #[ORM\JoinColumn(name: 'import_batch_id', referencedColumnName: 'id', nullable: false)]
    private BankStatementImport $importBatch;

    #[Groups(['bank_statement_line:read'])]
    #[ORM\Column(name: 'transaction_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $transactionDate;

    /**
     * Signed: positive = credit/abono, negative = debit/cargo. This is how a
     * bank export naturally reads and lets the matching service check both
     * amount magnitude and expected direction against the source movement.
     */
    #[Groups(['bank_statement_line:read'])]
    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[Groups(['bank_statement_line:read'])]
    #[ORM\Column(name: 'glosa', length: 255)]
    private string $glosa;

    #[Groups(['bank_statement_line:read'])]
    #[ORM\Column(name: 'reference', length: 100, nullable: true)]
    private ?string $reference = null;

    #[Groups(['bank_statement_line:read'])]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::UNMATCHED])]
    private string $status = self::UNMATCHED;

    #[Groups(['bank_statement_line:read'])]
    #[ORM\Column(name: 'row_no', nullable: true)]
    private ?int $rowNumber = null;

    #[Groups(['bank_statement_line:read'])]
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

    public function getBankAccount(): BankAccount
    {
        return $this->bankAccount;
    }

    public function setBankAccount(BankAccount $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getImportBatch(): BankStatementImport
    {
        return $this->importBatch;
    }

    public function setImportBatch(BankStatementImport $importBatch): static
    {
        $this->importBatch = $importBatch;

        return $this;
    }

    public function getTransactionDate(): DateTimeImmutable
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(DateTimeImmutable $transactionDate): static
    {
        $this->transactionDate = $transactionDate;

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

    public function getGlosa(): string
    {
        return $this->glosa;
    }

    public function setGlosa(string $glosa): static
    {
        $this->glosa = $glosa;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function setRowNumber(?int $rowNumber): static
    {
        $this->rowNumber = $rowNumber;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
