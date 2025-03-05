<?php

namespace App\CreditNote\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Entity\Sale;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\GeneratedValue;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: 'nota')]
class CreditNoteEntity
{
    public function __construct(
        #[ORM\Id]
        #[GeneratedValue(strategy: 'CUSTOM')]
        #[CustomIdGenerator(class: UuidGenerator::class)]
        #[ORM\Column(name: 'nota_id', type: 'string')]
        public ?string $nota_id = null,
        #[ORM\ManyToOne(targetEntity: Sale::class)]
        #[ORM\JoinColumn(name: 'movimiento_id', referencedColumnName: 'movimiento_id')]
        public ?Sale $sale = null,
        #[ORM\Column(name: 'raw_document_id', type: 'integer')]
        public ?int $raw_document_id = null,
        #[ORM\Column(name: 'fecha', type: 'date')]
        public ?\DateTimeInterface $fecha = null,
        #[ORM\Column(name: 'tipo_id', type: 'string')]
        public ?string $tipo_id = null,
        #[ORM\Column(name: 'codigo_motivo', type: 'string')]
        public ?string $codigo_motivo = null,
        #[ORM\Column(name: 'descripcion_motivo', type: 'string')]
        public ?string $descripcion_motivo = null,
        #[ORM\Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[ORM\Column(name: 'correlativo', type: 'integer')]
        public ?int $correlativo = null,
        #[ORM\Column(name: 'doc_afectado', type: 'string')]
        public ?string $doc_afectado = null,
        #[ORM\Column(name: 'digest', type: 'string')]
        public ?string $digest = null,
        #[ORM\Column(name: 'envio_sunat', type: 'string')]
        public ?string $envio_sunat = null,
        #[ORM\Column(name: 'mensaje_sunat', type: 'string')]
        public ?string $mensaje_sunat = null,
        #[ORM\Column(name: 'total', type: 'decimal')]
        public ?float $total = null,
        #[ORM\Column(name: 'created_by', type: 'integer')]
        public ?int $created_by = null,
        #[ORM\Column(name: 'updated_by', type: 'integer')]
        public ?int $updated_by = null,
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[ORM\Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null,
    ) {
    }
}
