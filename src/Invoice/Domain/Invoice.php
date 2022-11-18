<?php

namespace App\Invoice\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Invoice
{
    public function __construct(
        #[SerializedName('comprobante_id')]
        public ?string $comprobante_id,
        #[SerializedName('movimiento_id')]
        public ?string $movimiento_id,
        #[SerializedName('cliente_id')]
        public ?string $cliente_id,
        #[SerializedName('tipo_comprobante_id')]
        public ?string $tipo_comprobante_id,
        #[SerializedName('fecha')]
        public ?\DateTime $fecha,
        #[SerializedName('serie')]
        public ?string $serie,
        #[SerializedName('numero')]
        public ?string $numero,
        #[SerializedName('inafecto')]
        public ?bool $inafecto,
        #[SerializedName('subtotal')]
        public ?string $subtotal,
        #[SerializedName('gravada')]
        public ?string $gravada,
        #[SerializedName('igv')]
        public ?string $igv,
        #[SerializedName('rc')]
        public ?string $rc,
        #[SerializedName('total')]
        public ?string $total,
        #[SerializedName('discount_percent')]
        public ?int $discount_percent,
        #[SerializedName('discount_amount')]
        public ?string $discount_amount,
        #[SerializedName('voided')]
        public ?bool $voided,
        #[SerializedName('digest')]
        public ?string $digest,
        #[SerializedName('ticket_baja')]
        public ?string $ticket_baja,
        #[SerializedName('correlativo_baja')]
        public ?string $correlativo_baja,
        #[SerializedName('envio_sunat')]
        public ?string $envio_sunat,
        #[SerializedName('mensaje_sunat')]
        public ?string $mensaje_sunat,
        #[SerializedName('created_at')]
        public ?\DateTime $created_at,
        #[SerializedName('updated_at')]
        public ?\DateTime $updated_at,

    )
    {
    }
}