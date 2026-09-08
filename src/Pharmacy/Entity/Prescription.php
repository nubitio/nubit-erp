<?php

declare(strict_types=1);

namespace App\Pharmacy\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Pharmacy\Repository\PrescriptionRepository;
use App\Pharmacy\State\PrescriptionInput;
use App\Pharmacy\State\PrescriptionInputProcessor;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A prescription on file for a dispensing (NB-10 follow-up). Recorded by the
 * pharmacist for products whose {@see PharmacyProduct::requiresPrescription()}
 * is true; optionally linked to the sale that dispensed it.
 */
#[ORM\Entity(repositoryClass: PrescriptionRepository::class)]
#[ORM\Table(name: 'pharmacy_prescriptions')]
#[ORM\Index(name: 'idx_pharmacy_prescription_sale', columns: ['sale_id'])]
#[ApiResource(
    shortName: 'PharmacyPrescription',
    operations: [
        new GetCollection(uriTemplate: '/pharmacy/prescriptions'),
        new Get(uriTemplate: '/pharmacy/prescriptions/{id}'),
        new Post(
            uriTemplate: '/pharmacy/prescriptions',
            input: PrescriptionInput::class,
            processor: PrescriptionInputProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['pharmacy_prescription:read']],
    filters: [DataGridFilter::class],
)]
class Prescription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pharmacy_prescription:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pharmacy_prescription:read'])]
    private ?string $saleId = null;

    #[ORM\Column(length: 255)]
    #[Groups(['pharmacy_prescription:read'])]
    private string $patientName;

    #[ORM\Column(length: 32, nullable: true)]
    #[Groups(['pharmacy_prescription:read'])]
    private ?string $patientDocument = null;

    #[ORM\Column(length: 255)]
    #[Groups(['pharmacy_prescription:read'])]
    private string $prescriberName;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['pharmacy_prescription:read'])]
    private ?string $prescriberRegistration = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['pharmacy_prescription:read'])]
    private DateTimeImmutable $issuedOn;

    /** @var list<int> */
    #[ORM\Column(type: 'json')]
    #[Groups(['pharmacy_prescription:read'])]
    private array $productIds;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['pharmacy_prescription:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['pharmacy_prescription:read'])]
    private DateTimeImmutable $createdAt;

    /**
     * @param list<int> $productIds
     */
    public function __construct(
        string $patientName,
        string $prescriberName,
        DateTimeImmutable $issuedOn,
        array $productIds,
    ) {
        $this->patientName = $patientName;
        $this->prescriberName = $prescriberName;
        $this->issuedOn = $issuedOn;
        $this->productIds = $productIds;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleId(): ?string
    {
        return $this->saleId;
    }

    public function linkToSale(string $saleId): self
    {
        $this->saleId = $saleId;

        return $this;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function getPatientDocument(): ?string
    {
        return $this->patientDocument;
    }

    public function setPatientDocument(?string $patientDocument): self
    {
        $this->patientDocument = $patientDocument;

        return $this;
    }

    public function getPrescriberName(): string
    {
        return $this->prescriberName;
    }

    public function getPrescriberRegistration(): ?string
    {
        return $this->prescriberRegistration;
    }

    public function setPrescriberRegistration(?string $prescriberRegistration): self
    {
        $this->prescriberRegistration = $prescriberRegistration;

        return $this;
    }

    public function getIssuedOn(): DateTimeImmutable
    {
        return $this->issuedOn;
    }

    /**
     * @return list<int>
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function covers(int $productId): bool
    {
        return in_array($productId, $this->productIds, strict: true);
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
