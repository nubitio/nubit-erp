<?php

namespace App\Supplier\Infrastructure;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Supplier\Domain\Supplier;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'proveedor')]
class SupplierEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'proveedor_id', type: 'string')]
    public ?string $proveedor_id;
    #[Column(name: 'ruc', type: 'string')]
    public ?string $ruc;
    #[Column(name: 'razon_social', type: 'string')]
    public ?string $razon_social;
    #[Column(name: 'domicilio_fiscal', type: 'string')]
    public ?string $domicilio_fiscal;
    #[Column(name: 'telefono', type: 'string')]
    public ?string $telefono;
    #[Column(name: 'estado', type: 'boolean')]
    public ?bool $estado;

    public function mapToDomainModel(): Supplier
    {
        return new Supplier(
            $this->proveedor_id,
            $this->ruc,
            $this->razon_social,
            $this->domicilio_fiscal,
            $this->telefono,
            $this->estado,
        );
    }

    public function mapFromDomainModel(Supplier $supplier): SupplierEntity
    {
        $this->proveedor_id = $supplier->proveedor_id;
        $this->ruc = $supplier->ruc;
        $this->razon_social = $supplier->razon_social;
        $this->domicilio_fiscal = $supplier->domicilio_fiscal;
        $this->telefono = $supplier->telefono;
        $this->estado = $supplier->estado;

        return $this;
    }
}
