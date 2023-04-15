<?php

namespace App\Invoice\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping as ORM;

#[IgnoreAutowire]
#[ORM\Entity]
#[ORM\Table(name: "comprobante")]
class InvoiceEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(name: 'comprobante_id', type: 'string')]
        public ?string $comprobante_id = null,
        #[ORM\Column(name: 'movimiento_id', type: 'string')]
        public ?string $movimiento_id = null,
        #[ORM\Column(name: 'cliente_id', type: 'string')]
        public ?string $cliente_id = null,
        #[ORM\Column(name: 'tipo_comprobante_id', type: 'string')]
        public ?string $tipo_comprobante_id = null,
        #[ORM\Column(name: 'fecha', type: 'date')]
        public ?\DateTimeInterface $fecha = null,
        #[ORM\Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[ORM\Column(name: 'numero', type: 'string')]
        public ?string $numero = null,
        #[ORM\Column(name: 'inafecto', type: 'boolean')]
        public ?bool $inafecto = null,
        #[ORM\Column(name: 'subtotal', type: 'decimal')]
        public ?float $subtotal = null,
        #[ORM\Column(name: 'gravada', type: 'decimal')]
        public ?float $gravada = null,
        #[ORM\Column(name: 'igv', type: 'decimal')]
        public ?float $igv = null,
        #[ORM\Column(name: 'rc', type: 'decimal')]
        public ?float $rc = null,
        #[ORM\Column(name: 'total', type: 'decimal')]
        public ?float $total = null,
        #[ORM\Column(name: 'discount_percent', type: 'integer')]
        public ?int $discount_percent = null,
        #[ORM\Column(name: 'discount_amount', type: 'decimal')]
        public ?float $discount_amount = null,
        #[ORM\Column(name: 'voided', type: 'boolean')]
        public ?bool $voided = null,
        #[ORM\Column(name: 'digest', type: 'string')]
        public ?string $digest = null,
        #[ORM\Column(name: 'ticket_baja', type: 'string')]
        public ?string $ticket_baja = null,
        #[ORM\Column(name: 'correlativo_baja', type: 'string')]
        public ?string $correlativo_baja = null,
        #[ORM\Column(name: 'envio_sunat', type: 'string')]
        public ?string $envio_sunat = null,
        #[ORM\Column(name: 'mensaje_sunat', type: 'text')]
        public ?string $mensaje_sunat = null,
        #[ORM\Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[ORM\Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null
    )
    {
    }
}