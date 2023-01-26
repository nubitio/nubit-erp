<?php

namespace App\Sale\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Domain\Customer;
use App\Invoice\Domain\Invoice;
use App\PaymentMethod\Domain\PaymentMethod;
use App\SaleDetail\Domain\SaleDetail;
use App\Warehouse\Domain\Warehouse;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Sale
{
    public function __construct(
        #[SerializedName('movimiento_id')]
        public ?string             $movimiento_id = null,
        #[SerializedName('operacion_id')]
        public ?string             $operacion_id = null,
        #[SerializedName('almacen')]
        public ?Warehouse          $almacen = null,
        #[SerializedName('cliente')]
        public ?Customer           $cliente = null,
        #[SerializedName('tipo_id')]
        public ?int                $tipo_id = null,
        #[SerializedName('fecha')]
        public ?\DateTimeInterface $fecha = null,
        #[SerializedName('proveedor_id')]
        public ?string             $proveedor_id = null,
        #[SerializedName('tipo_pago')]
        public ?PaymentMethod      $tipo_pago = null,
        #[SerializedName('tipo_tarjeta')]
        public ?int                $tipo_tarjeta = null,
        #[SerializedName('serie')]
        public ?string             $serie = null,
        #[SerializedName('numero')]
        public ?string             $numero = null,
        #[SerializedName('descuento')]
        public ?float              $descuento = null,
        #[SerializedName('glosa')]
        public ?string             $glosa = null,
        #[SerializedName('orden_id')]
        public ?int                $orden_id = null,
        #[SerializedName('has_invoice')]
        public ?bool               $has_invoice = false,
        #[SerializedName('is_consume')]
        public ?bool               $is_consume = false,
        #[SerializedName('caja_id')]
        public ?string             $caja_id = null,
        #[SerializedName('estado')]
        public ?bool               $estado = false,
        #[SerializedName('date_created')]
        public ?\DateTimeInterface $date_created = null,
        #[SerializedName('date_updated')]
        public ?\DateTimeInterface $date_updated = null,
        #[SerializedName('created_by')]
        public ?int                $created_by = null,
        #[SerializedName('updated_by')]
        public ?int                $updated_by = null,
        #[SerializedName('igv')]
        public ?int                $igv = null,
        #[SerializedName('recargo_consumo')]
        public ?int                $recargo_consumo = null,
        /** @var SaleDetail[] $details */
        #[SerializedName('details')]
        public array               $details = [],
        #[SerializedName('comprobante')]
        public ?Invoice            $invoice = null
    )
    {
    }
}