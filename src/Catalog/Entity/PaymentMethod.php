<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Catalog\Repository\PaymentMethodRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ApiResource(mercure: true)]
class PaymentMethod
{
    final public const int CASH = 1;

    #[Groups([
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups([
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ApiProperty(description: 'payment_method.name.label')]
    #[ORM\Column(length: 255)]
    private string $name;

    /** When false the payment method is hidden from the POS and new sales. */
    #[Groups([
        'cash_movement:read',
        'sale:read',
        'sale_record:read',
    ])]
    #[ApiProperty(description: 'payment_method.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
