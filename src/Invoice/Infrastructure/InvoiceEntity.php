<?php

namespace App\Invoice\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'comprobante')]
class InvoiceEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue]
        #[Column(name: 'comprobante_id', type: 'string')]
        public ?string             $comprobante_id = null,
        #[Column(name: 'movimiento_id', type: 'string')]
        public ?string             $movimiento_id = null,
        #[Column(name: 'cliente_id', type: 'string')]
        public ?string             $cliente_id = null,
        #[Column(name: 'tipo_comprobante_id', type: 'string')]
        public ?string             $tipo_comprobante_id = null,
        #[Column(name: 'fecha', type: 'date')]
        public ?\DateTimeInterface $fecha = null,
        #[Column(name: 'serie', type: 'string')]
        public ?string             $serie = null,
        #[Column(name: 'numero', type: 'string')]
        public ?string             $numero = null,
        #[Column(name: 'inafecto', type: 'boolean')]
        public ?bool               $inafecto = null,
        #[Column(name: 'subtotal', type: 'decimal')]
        public ?float              $subtotal = null,
        #[Column(name: 'gravada', type: 'decimal')]
        public ?float              $gravada = null,
        #[Column(name: 'igv', type: 'decimal')]
        public ?float              $igv = null,
        #[Column(name: 'rc', type: 'decimal')]
        public ?float              $rc = null,
        #[Column(name: 'total', type: 'decimal')]
        public ?float              $total = null,
        #[Column(name: 'discount_percent', type: 'integer')]
        public ?int                $discount_percent = null,
        #[Column(name: 'discount_amount', type: 'decimal')]
        public ?float              $discount_amount = null,
        #[Column(name: 'voided', type: 'boolean')]
        public ?bool               $voided = null,
        #[Column(name: 'digest', type: 'string')]
        public ?string             $digest = null,
        #[Column(name: 'ticket_baja', type: 'string')]
        public ?string             $ticket_baja = null,
        #[Column(name: 'correlativo_baja', type: 'string')]
        public ?string             $correlativo_baja = null,
        #[Column(name: 'envio_sunat', type: 'string')]
        public ?string             $envio_sunat = null,
        #[Column(name: 'mensaje_sunat', type: 'text')]
        public ?string             $mensaje_sunat = null,
        #[Column(name: 'created_at', type: 'datetime')]
        public ?\DateTimeInterface $created_at = null,
        #[Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTimeInterface $updated_at = null
    )
    {
    }
}