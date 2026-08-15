<?php

declare(strict_types=1);

namespace App\BankReconciliation\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\BankReconciliation\Repository\BankAccountRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BankAccountRepository::class)]
#[ORM\Table(name: 'bank_account')]
#[ORM\Index(name: 'idx_bank_account_active', columns: ['active'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['bank_account:read']],
    denormalizationContext: ['groups' => ['bank_account:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class BankAccount
{
    #[Groups(['bank_account:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotBlank]
    #[Groups(['bank_account:read', 'bank_account:write'])]
    #[ORM\Column(name: 'bank', length: 100)]
    private string $bank;

    #[Assert\NotBlank]
    #[Groups(['bank_account:read', 'bank_account:write'])]
    #[ORM\Column(name: 'alias', length: 100)]
    private string $alias;

    #[Assert\NotBlank]
    #[Groups(['bank_account:read', 'bank_account:write'])]
    #[ORM\Column(name: 'account_number', length: 50)]
    private string $accountNumber;

    #[Groups(['bank_account:read', 'bank_account:write'])]
    #[ORM\Column(name: 'cci', length: 50, nullable: true)]
    private ?string $cci = null;

    #[Groups(['bank_account:read', 'bank_account:write'])]
    #[ORM\Column(name: 'currency', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Groups(['bank_account:read', 'bank_account:write'])]
    #[ORM\Column(name: 'active', options: ['default' => true])]
    private bool $active = true;

    #[Groups(['bank_account:read'])]
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

    public function getBank(): string
    {
        return $this->bank;
    }

    public function setBank(string $bank): static
    {
        $this->bank = $bank;

        return $this;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getCci(): ?string
    {
        return $this->cci;
    }

    public function setCci(?string $cci): static
    {
        $this->cci = $cci;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
