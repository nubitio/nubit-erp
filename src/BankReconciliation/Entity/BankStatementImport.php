<?php

declare(strict_types=1);

namespace App\BankReconciliation\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\BankReconciliation\Dto\BankStatementImportAutoMatchDto;
use App\BankReconciliation\Repository\BankStatementImportRepository;
use App\BankReconciliation\State\BankStatementImportAutoMatchProcessor;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One "import a bank statement" job. Created only by
 * BankStatementImportController::upload() (multipart file upload, not a
 * plain ApiResource Post — same reasoning as ProductImportController).
 */
#[ORM\Entity(repositoryClass: BankStatementImportRepository::class)]
#[ORM\Table(name: 'bank_statement_import')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            uriTemplate: '/bank-statement-imports/{id}/auto-match',
            // See BankStatementLine's custom operations for why this
            // override is required whenever the class-level
            // denormalizationContext uses groups.
            denormalizationContext: [],
            input: BankStatementImportAutoMatchDto::class,
            output: BankStatementImport::class,
            processor: BankStatementImportAutoMatchProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['bank_statement_import:read']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class BankStatementImport
{
    public const string PROCESSING = 'PROCESSING';

    public const string COMPLETED = 'COMPLETED';

    public const string FAILED = 'FAILED';

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['bank_statement_import:read'])]
    #[ApiProperty(description: 'bank_statement_import.bank_account.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: BankAccount::class)]
    #[ORM\JoinColumn(name: 'bank_account_id', referencedColumnName: 'id', nullable: false)]
    private BankAccount $bankAccount;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'file_name', length: 255)]
    private string $fileName;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'bank_profile', length: 30)]
    private string $bankProfile;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::PROCESSING])]
    private string $status = self::PROCESSING;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'total_rows', options: ['default' => 0])]
    private int $totalRows = 0;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'imported_rows', options: ['default' => 0])]
    private int $importedRows = 0;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'error_rows', options: ['default' => 0])]
    private int $errorRows = 0;

    /** @var list<array{row: int, message: string}>|null */
    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'errors', type: Types::JSON, nullable: true)]
    private ?array $errors = null;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'usuario_id', nullable: false)]
    private User $createdBy;

    #[Groups(['bank_statement_import:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
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

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getBankProfile(): string
    {
        return $this->bankProfile;
    }

    public function setBankProfile(string $bankProfile): static
    {
        $this->bankProfile = $bankProfile;

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

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function setTotalRows(int $totalRows): static
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    public function setImportedRows(int $importedRows): static
    {
        $this->importedRows = $importedRows;

        return $this;
    }

    public function getErrorRows(): int
    {
        return $this->errorRows;
    }

    public function setErrorRows(int $errorRows): static
    {
        $this->errorRows = $errorRows;

        return $this;
    }

    /** @return list<array{row: int, message: string}>|null */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /** @param list<array{row: int, message: string}>|null $errors */
    public function setErrors(?array $errors): static
    {
        $this->errors = $errors;

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
