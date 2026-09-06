<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Catalog\Enum\SettlementBehavior;
use App\Catalog\Repository\PaymentMethodRepository;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ApiResource(filters: [DataGridFilter::class], mercure: true, normalizationContext: ['groups' => [
    'payment_method:read',
]])]
class PaymentMethod
{
    final public const int CASH = 1;
    final public const int CREDIT = 2;

    #[Groups([
        'payment_method:read',
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups([
        'payment_method:read',
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ApiProperty(description: 'payment_method.name.label')]
    #[ORM\Column(length: 255)]
    private string $name;

    /** When false the payment method is hidden from the POS and new sales. */
    #[Groups([
        'payment_method:read',
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ApiProperty(description: 'payment_method.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[Groups([
        'payment_method:read',
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ORM\Column(
        name: 'settlement_behavior',
        type: 'string',
        length: 16,
        enumType: SettlementBehavior::class,
        options: ['default' => 'COLLECTED'],
    )]
    private SettlementBehavior $settlementBehavior = SettlementBehavior::COLLECTED;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSettlementBehavior(): SettlementBehavior
    {
        return $this->settlementBehavior;
    }

    public function setSettlementBehavior(SettlementBehavior $settlementBehavior): static
    {
        $this->settlementBehavior = $settlementBehavior;

        return $this;
    }
}
