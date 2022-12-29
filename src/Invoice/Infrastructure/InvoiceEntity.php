<?php

namespace App\Invoice\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Invoice\Domain\Invoice;
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
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'comprobante_id', type: 'string')]
    public ?string $comprobante_id;
    #[Column(name: 'movimiento_id', type: 'string')]
    public ?string $movimiento_id;
    #[Column(name: 'cliente_id', type: 'string')]
    public ?string $cliente_id;
    #[Column(name: 'tipo_comprobante_id', type: 'string')]
    public ?string $tipo_comprobante_id;
    #[Column(name: 'fecha', type: 'date')]
    public ?\DateTime $fecha;
    #[Column(name: 'serie', type: 'string')]
    public ?string $serie;
    #[Column(name: 'numero', type: 'string')]
    public ?string $numero;
    #[Column(name: 'inafecto', type: 'boolean')]
    public ?bool $inafecto;
    #[Column(name: 'subtotal', type: 'decimal')]
    public ?string $subtotal;
    #[Column(name: 'gravada', type: 'decimal')]
    public ?string $gravada;
    #[Column(name: 'igv', type: 'decimal')]
    public ?string $igv;
    #[Column(name: 'rc', type: 'decimal')]
    public ?string $rc;
    #[Column(name: 'total', type: 'decimal')]
    public ?string $total;
    #[Column(name: 'discount_percent', type: 'integer')]
    public ?int $discount_percent;
    #[Column(name: 'discount_amount', type: 'decimal')]
    public ?string $discount_amount;
    #[Column(name: 'voided', type: 'boolean')]
    public ?bool $voided;
    #[Column(name: 'digest', type: 'string')]
    public ?string $digest;
    #[Column(name: 'ticket_baja', type: 'string')]
    public ?string $ticket_baja;
    #[Column(name: 'correlativo_baja', type: 'string')]
    public ?string $correlativo_baja;
    #[Column(name: 'envio_sunat', type: 'string')]
    public ?string $envio_sunat;
    #[Column(name: 'mensaje_sunat', type: 'text')]
    public ?string $mensaje_sunat;
    #[Column(name: 'created_at', type: 'datetime')]
    public ?\DateTime $created_at;
    #[Column(name: 'updated_at', type: 'datetime')]
    public ?\DateTime $updated_at;

    public function mapToDomainModel(): Invoice
    {
        return new Invoice(
            $this->comprobante_id,
            $this->movimiento_id,
            $this->cliente_id,
            $this->tipo_comprobante_id,
            $this->fecha,
            $this->serie,
            $this->numero,
            $this->inafecto,
            $this->subtotal,
            $this->gravada,
            $this->igv,
            $this->rc,
            $this->total,
            $this->discount_percent,
            $this->discount_amount,
            $this->voided,
            $this->digest,
            $this->ticket_baja,
            $this->correlativo_baja,
            $this->envio_sunat,
            $this->mensaje_sunat,
            $this->created_at,
            $this->updated_at,
        );
    }

    public function mapFromDomainModel(Invoice $invoice): InvoiceEntity
    {
        $this->comprobante_id = $invoice->comprobante_id ?? $this->comprobante_id;
        $this->movimiento_id = $invoice->movimiento_id ?? $this->movimiento_id;
        $this->cliente_id = $invoice->cliente_id ?? $this->cliente_id;
        $this->tipo_comprobante_id = $invoice->tipo_comprobante_id ?? $this->tipo_comprobante_id;
        $this->fecha = $invoice->fecha ?? $this->fecha;
        $this->serie = $invoice->serie ?? $this->serie;
        $this->numero = $invoice->numero ?? $this->numero;
        $this->inafecto = $invoice->inafecto ?? $this->inafecto;
        $this->subtotal = $invoice->subtotal ?? $this->subtotal;
        $this->gravada = $invoice->gravada ?? $this->gravada;
        $this->igv = $invoice->igv ?? $this->igv;
        $this->rc = $invoice->rc ?? $this->rc;
        $this->total = $invoice->total ?? $this->total;
        $this->discount_percent = $invoice->discount_percent ?? $this->discount_percent;
        $this->discount_amount = $invoice->discount_amount ?? $this->discount_amount;
        $this->voided = $invoice->voided ?? $this->voided;
        $this->digest = $invoice->digest ?? $this->digest;
        $this->ticket_baja = $invoice->ticket_baja ?? $this->ticket_baja;
        $this->correlativo_baja = $invoice->correlativo_baja ?? $this->correlativo_baja;
        $this->envio_sunat = $invoice->envio_sunat ?? $this->envio_sunat;
        $this->mensaje_sunat = $invoice->mensaje_sunat ?? $this->mensaje_sunat;
        $this->created_at = $invoice->created_at ?? $this->created_at;
        $this->updated_at = $invoice->updated_at ?? $this->updated_at;

        return $this;
    }
}
