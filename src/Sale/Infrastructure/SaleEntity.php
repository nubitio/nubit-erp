<?php

namespace App\Sale\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Infrastructure\CustomerEntity;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use App\Warehouse\Infrastructure\WarehouseEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'movimiento')]
class SaleEntity
{
    public function __construct(
        #[Id]
        #[GeneratedValue(strategy: 'CUSTOM')]
        #[CustomIdGenerator(class: UuidGenerator::class)]
        #[Column(name: 'movimiento_id', type: 'string')]
        public ?string              $movimiento_id = null,
        #[Column(name: 'operacion_id', type: 'string')]
        public ?string              $operacion_id = null,
        #[ManyToOne(targetEntity: WarehouseEntity::class)]
        #[JoinColumn(name: 'almacen_id', referencedColumnName: 'almacen_id')]
        public ?WarehouseEntity     $almacen = null,
        #[ManyToOne(targetEntity: CustomerEntity::class)]
        #[JoinColumn(name: 'cliente_id', referencedColumnName: 'ruc')]
        public ?CustomerEntity      $cliente = null,
        #[Column(name: 'tipo_id', type: 'integer')]
        public ?int                 $tipo_id = null,
        #[Column(name: 'fecha', type: 'date')]
        public ?\DateTimeInterface  $fecha = null,
        #[Column(name: 'proveedor_id', type: 'string')]
        public ?string              $proveedor_id = null,
        #[ManyToOne(targetEntity: PaymentMethodEntity::class)]
        #[JoinColumn(name: 'tipo_pago', referencedColumnName: 'id')]
        public ?PaymentMethodEntity $tipo_pago = null,
        #[Column(name: 'tipo_tarjeta', type: 'integer')]
        public ?int                 $tipo_tarjeta = null,
        #[Column(name: 'serie', type: 'string')]
        public ?string              $serie = null,
        #[Column(name: 'numero', type: 'string')]
        public ?string              $numero = null,
        #[Column(name: 'descuento', type: 'decimal')]
        public ?float               $descuento = null,
        #[Column(name: 'glosa', type: 'string')]
        public ?string              $glosa = null,
        #[Column(name: 'orden_id', type: 'integer')]
        public ?int                 $orden_id = null,
        #[Column(name: 'has_invoice', type: 'boolean')]
        public ?bool                $has_invoice = null,
        #[Column(name: 'is_consume', type: 'boolean')]
        public ?bool                $is_consume = null,
        #[Column(name: 'caja_id', type: 'string')]
        public ?string              $caja_id = null,
        #[Column(name: 'estado', type: 'boolean')]
        public ?bool                $estado = null,
        #[Column(name: 'date_created', type: 'datetime')]
        public ?\DateTimeInterface  $date_created = null,
        #[Column(name: 'date_updated', type: 'datetime')]
        public ?\DateTimeInterface  $date_updated = null,
        #[Column(name: 'created_by', type: 'integer')]
        public ?int                 $created_by = null,
        #[Column(name: 'updated_by', type: 'integer')]
        public ?int                 $updated_by = null,
        #[Column(name: 'igv', type: 'integer')]
        public ?int                 $igv = null,
        #[Column(name: 'recargo_consumo', type: 'integer')]
        public ?int                 $recargo_consumo = null,
        #[Column(name: 'invoice', type: 'string')]
        public ?string              $invoice = null,
        #[Column(name: 'total', type: 'decimal')]
        public ?float               $total = null,
        /** @var SaleDetailEntity[] $details */
        #[OneToMany(mappedBy: 'movimiento', targetEntity: SaleDetailEntity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
        public Collection|array     $details = new ArrayCollection()
    )
    {
    }
}