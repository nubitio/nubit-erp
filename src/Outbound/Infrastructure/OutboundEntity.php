<?php

namespace App\Outbound\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'movimiento')]
class OutboundEntity
{
    #[Id]
    #[Column(name: 'movimiento_id', type: 'string')]
    public ?string $movimiento_id;
    #[Column(name: 'operacion_id', type: 'string')]
    public ?string $operacion_id;
    #[Column(name: 'almacen_id', type: 'string')]
    public ?string $almacen_id;
    #[Column(name: 'cliente_id', type: 'string')]
    public ?string $cliente_id;
    #[Column(name: 'tipo_id', type: 'integer')]
    public ?int $tipo_id;
    #[Column(name: 'fecha', type: 'date')]
    public ?\DateTime $fecha;
    #[Column(name: 'proveedor_id', type: 'string')]
    public ?string $proveedor_id;
    #[Column(name: 'tipo_pago', type: 'integer')]
    public ?int $tipo_pago;
    #[Column(name: 'tipo_tarjeta', type: 'integer')]
    public ?int $tipo_tarjeta;
    #[Column(name: 'serie', type: 'string')]
    public ?string $serie;
    #[Column(name: 'numero', type: 'string')]
    public ?string $numero;
    #[Column(name: 'descuento', type: 'decimal')]
    public ?string $descuento;
    #[Column(name: 'glosa', type: 'string')]
    public ?string $glosa;
    #[Column(name: 'orden_id', type: 'integer')]
    public ?int $orden_id;
    #[Column(name: 'has_invoice', type: 'boolean')]
    public ?bool $has_invoice;
    #[Column(name: 'is_consume', type: 'boolean')]
    public ?bool $is_consume;
    #[Column(name: 'caja_id', type: 'string')]
    public ?string $caja_id;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;
    #[Column(name: 'date_created', type: 'datetime')]
    public ?\DateTime $date_created;
    #[Column(name: 'date_updated', type: 'datetime')]
    public ?\DateTime $date_updated;
    #[Column(name: 'created_by', type: 'integer')]
    public ?int $created_by;
    #[Column(name: 'updated_by', type: 'integer')]
    public ?int $updated_by;
    #[Column(name: 'igv', type: 'integer')]
    public ?int $igv;
    #[Column(name: 'recargo_consumo', type: 'integer')]
    public ?int $recargo_consumo;
}
