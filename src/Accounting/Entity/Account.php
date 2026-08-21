<?php

declare(strict_types=1);

namespace App\Accounting\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Accounting\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'chart_of_accounts')]
#[ApiResource(
    shortName: 'GlAccount',
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['account:read']],
    filters: [DataGridFilter::class],
    security: "is_granted('perm:accounting.view')",
)]
class Account
{
    public const string ASSET = 'ASSET';

    public const string LIABILITY = 'LIABILITY';

    public const string EQUITY = 'EQUITY';

    public const string INCOME = 'INCOME';

    public const string EXPENSE = 'EXPENSE';

    #[Groups(['account:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['account:read'])]
    #[ApiProperty(description: 'account.code.label')]
    #[ORM\Column(name: 'code', length: 16)]
    private string $code = '';

    #[Groups(['account:read'])]
    #[ApiProperty(description: 'account.name.label')]
    #[ORM\Column(name: 'name', length: 160)]
    private string $name = '';

    #[Groups(['account:read'])]
    #[ApiProperty(description: 'account.type.label')]
    #[ORM\Column(name: 'type', length: 20)]
    private string $type = self::ASSET;

    #[Groups(['account:read'])]
    #[ORM\Column(name: 'is_header', type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $header = false;

    #[Groups(['account:read'])]
    #[ORM\Column(name: 'parent_code', length: 16, nullable: true)]
    private ?string $parentCode = null;

    #[Groups(['account:read'])]
    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => 1])]
    private bool $active = true;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isHeader(): bool
    {
        return $this->header;
    }

    public function setHeader(bool $header): static
    {
        $this->header = $header;

        return $this;
    }

    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }

    public function setParentCode(?string $parentCode): static
    {
        $this->parentCode = $parentCode;

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
}
