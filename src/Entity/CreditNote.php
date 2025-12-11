<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\CreditNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'CreditNote',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['creditnote:read']],
    denormalizationContext: ['groups' => ['creditnote:write']],
    filters: [DevExtremeFilter::class],
)]
#[ORM\Entity(repositoryClass: CreditNoteRepository::class)]
#[ORM\Table(name: 'nota')]
#[ORM\HasLifecycleCallbacks]
class CreditNote
{
    #[Groups(['creditnote:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'nota_id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\ManyToOne(targetEntity: Sale::class)]
    #[ORM\JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id', nullable: false)]
    private Sale $sale;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'raw_document_id', type: Types::INTEGER, nullable: true)]
    private ?int $rawDocumentId = null;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'fecha', type: Types::DATE_MUTABLE, nullable: false)]
    private DateTimeInterface $fecha;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'tipo_id', type: Types::STRING, length: 10)]
    private string $tipoId;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'codigo_motivo', type: Types::STRING, length: 10)]
    private string $codigoMotivo;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'descripcion_motivo', type: Types::STRING, length: 255)]
    private string $descripcionMotivo;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'serie', type: Types::STRING, length: 10)]
    private string $serie;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'correlativo', type: Types::INTEGER)]
    private int $correlativo;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'doc_afectado', type: Types::STRING, length: 50, nullable: true)]
    private ?string $docAfectado = null;

    #[Groups(['creditnote:read'])]
    #[ORM\Column(name: 'digest', type: Types::STRING, length: 255, nullable: true)]
    private ?string $digest = null;

    #[Groups(['creditnote:read'])]
    #[ORM\Column(name: 'envio_sunat', type: Types::STRING, length: 50, nullable: true)]
    private ?string $envioSunat = null;

    #[Groups(['creditnote:read'])]
    #[ORM\Column(name: 'mensaje_sunat', type: Types::TEXT, nullable: true)]
    private ?string $mensajeSunat = null;

    #[Groups(['creditnote:read', 'creditnote:write'])]
    #[ORM\Column(name: 'total', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total = '0.00';

    #[Groups(['creditnote:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'usuario_id', nullable: true)]
    private ?User $createdBy = null;

    #[Groups(['creditnote:read'])]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by', referencedColumnName: 'usuario_id', nullable: true)]
    private ?User $updatedBy = null;

    #[Groups(['creditnote:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private DateTimeInterface $createdAt;

    #[Groups(['creditnote:read'])]
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new DateTime();
    }

    // Getters and Setters

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSale(): Sale
    {
        return $this->sale;
    }

    public function setSale(Sale $sale): self
    {
        $this->sale = $sale;

        return $this;
    }

    public function getRawDocumentId(): ?int
    {
        return $this->rawDocumentId;
    }

    public function setRawDocumentId(?int $rawDocumentId): self
    {
        $this->rawDocumentId = $rawDocumentId;

        return $this;
    }

    public function getFecha(): DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getTipoId(): string
    {
        return $this->tipoId;
    }

    public function setTipoId(string $tipoId): self
    {
        $this->tipoId = $tipoId;

        return $this;
    }

    public function getCodigoMotivo(): string
    {
        return $this->codigoMotivo;
    }

    public function setCodigoMotivo(string $codigoMotivo): self
    {
        $this->codigoMotivo = $codigoMotivo;

        return $this;
    }

    public function getDescripcionMotivo(): string
    {
        return $this->descripcionMotivo;
    }

    public function setDescripcionMotivo(string $descripcionMotivo): self
    {
        $this->descripcionMotivo = $descripcionMotivo;

        return $this;
    }

    public function getSerie(): string
    {
        return $this->serie;
    }

    public function setSerie(string $serie): self
    {
        $this->serie = $serie;

        return $this;
    }

    public function getCorrelativo(): int
    {
        return $this->correlativo;
    }

    public function setCorrelativo(int $correlativo): self
    {
        $this->correlativo = $correlativo;

        return $this;
    }

    public function getDocAfectado(): ?string
    {
        return $this->docAfectado;
    }

    public function setDocAfectado(?string $docAfectado): self
    {
        $this->docAfectado = $docAfectado;

        return $this;
    }

    public function getDigest(): ?string
    {
        return $this->digest;
    }

    public function setDigest(?string $digest): self
    {
        $this->digest = $digest;

        return $this;
    }

    public function getEnvioSunat(): ?string
    {
        return $this->envioSunat;
    }

    public function setEnvioSunat(?string $envioSunat): self
    {
        $this->envioSunat = $envioSunat;

        return $this;
    }

    public function getMensajeSunat(): ?string
    {
        return $this->mensajeSunat;
    }

    public function setMensajeSunat(?string $mensajeSunat): self
    {
        $this->mensajeSunat = $mensajeSunat;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get formatted document number (serie-correlativo)
     */
    public function getDocumentNumber(): string
    {
        return sprintf('%s-%s', $this->serie, str_pad((string)$this->correlativo, 8, '0', STR_PAD_LEFT));
    }

    /**
     * Check if a credit note has been sent to SUNAT
     */
    public function isSentToSunat(): bool
    {
        return !in_array($this->envioSunat, [null, '', '0', 'pendiente'], true);
    }

    /**
     * Check if a credit note was accepted by SUNAT
     */
    public function isAccepted(): bool
    {
        return $this->envioSunat === 'aceptado';
    }
}
