<?php

declare(strict_types=1);

namespace App\Pharmacy\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Pharmacy\Enum\ExpiryControl;
use App\Pharmacy\Repository\PharmacyProductRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Pharmacy-specific facts about a catalogue product (NB-10). Composition, not
 * inheritance: `Catalog\Product` (the `producto` table) is never touched — this
 * row is linked by `productId` only. One profile per product.
 */
#[ORM\Entity(repositoryClass: PharmacyProductRepository::class)]
#[ORM\Table(name: 'pharmacy_products')]
#[ORM\UniqueConstraint(name: 'uniq_pharmacy_product', columns: ['product_id'])]
#[ApiResource(
    shortName: 'PharmacyProduct',
    operations: [
        new GetCollection(uriTemplate: '/pharmacy/products'),
        new Get(uriTemplate: '/pharmacy/products/{id}'),
    ],
    normalizationContext: ['groups' => ['pharmacy_product:read']],
    filters: [DataGridFilter::class],
)]
class PharmacyProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pharmacy_product:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['pharmacy_product:read'])]
    private int $productId;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacy_product:read'])]
    private ?string $activeIngredient = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['pharmacy_product:read'])]
    private ?string $sanitaryRegistration = null;

    #[ORM\Column]
    #[Groups(['pharmacy_product:read'])]
    private bool $requiresPrescription = false;

    #[ORM\Column]
    #[Groups(['pharmacy_product:read'])]
    private bool $controlled = false;

    /** DIGEMID controlled-substance list code, when {@see $controlled} is true. */
    #[ORM\Column(length: 32, nullable: true)]
    #[Groups(['pharmacy_product:read'])]
    private ?string $controlledSubstanceCode = null;

    #[ORM\Column(enumType: ExpiryControl::class, length: 10)]
    #[Groups(['pharmacy_product:read'])]
    private ExpiryControl $expiryControl = ExpiryControl::None;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(int $productId)
    {
        $this->productId = $productId;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getActiveIngredient(): ?string
    {
        return $this->activeIngredient;
    }

    public function setActiveIngredient(?string $activeIngredient): self
    {
        $this->activeIngredient = $activeIngredient;
        $this->touch();

        return $this;
    }

    public function getSanitaryRegistration(): ?string
    {
        return $this->sanitaryRegistration;
    }

    public function setSanitaryRegistration(?string $sanitaryRegistration): self
    {
        $this->sanitaryRegistration = $sanitaryRegistration;
        $this->touch();

        return $this;
    }

    public function requiresPrescription(): bool
    {
        return $this->requiresPrescription;
    }

    public function setRequiresPrescription(bool $requiresPrescription): self
    {
        $this->requiresPrescription = $requiresPrescription;
        $this->touch();

        return $this;
    }

    public function isControlled(): bool
    {
        return $this->controlled;
    }

    public function setControlled(bool $controlled): self
    {
        $this->controlled = $controlled;
        $this->touch();

        return $this;
    }

    public function getControlledSubstanceCode(): ?string
    {
        return $this->controlledSubstanceCode;
    }

    public function setControlledSubstanceCode(?string $controlledSubstanceCode): self
    {
        $this->controlledSubstanceCode = $controlledSubstanceCode;
        $this->touch();

        return $this;
    }

    public function getExpiryControl(): ExpiryControl
    {
        return $this->expiryControl;
    }

    public function setExpiryControl(ExpiryControl $expiryControl): self
    {
        $this->expiryControl = $expiryControl;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
