<?php

namespace App\Invoice\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Domain\Customer;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Invoice
{
    public function __construct(
        #[SerializedName('comprobante_id')]
        public ?string             $comprobante_id = null,
        #[SerializedName('movimiento_id')]
        public ?string             $movimiento_id = null,
        #[SerializedName('cliente')]
        public ?Customer           $cliente = null,
        #[SerializedName('tipo_comprobante_id')]
        public ?string             $tipo_comprobante_id = null,
        #[SerializedName('fecha')]
        public ?\DateTimeInterface $fecha = null,
        #[SerializedName('serie')]
        public ?string             $serie = null,
        #[SerializedName('numero')]
        public ?string             $numero = null,
        #[SerializedName('inafecto')]
        public ?bool               $inafecto = false,
        #[SerializedName('subtotal')]
        public ?float              $subtotal = null,
        #[SerializedName('gravada')]
        public ?float              $gravada = null,
        #[SerializedName('igv')]
        public ?float              $igv = null,
        #[SerializedName('rc')]
        public ?float              $rc = null,
        #[SerializedName('total')]
        public ?float              $total = null,
        #[SerializedName('discount_percent')]
        public ?int                $discount_percent = null,
        #[SerializedName('discount_amount')]
        public ?float              $discount_amount = null,
        #[SerializedName('voided')]
        public ?bool               $voided = false,
        #[SerializedName('digest')]
        public ?string             $digest = null,
        #[SerializedName('ticket_baja')]
        public ?string             $ticket_baja = null,
        #[SerializedName('correlativo_baja')]
        public ?string             $correlativo_baja = null,
        #[SerializedName('envio_sunat')]
        public ?string             $envio_sunat = null,
        #[SerializedName('mensaje_sunat')]
        public ?string             $mensaje_sunat = null,
        #[SerializedName('created_at')]
        public ?\DateTimeInterface $created_at = null,
        #[SerializedName('updated_at')]
        public ?\DateTimeInterface $updated_at = null
    )
    {
    }
}