<?php

namespace App\VoucherType\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\VoucherType\Domain\VoucherType;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "tipo_comprobante")]
class VoucherTypeEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "codigo", type: "string")]
    public ?string $codigo;
    #[Column(name: "descripcion", type: "string")]
    public ?string $descripcion;
    #[Column(name: "estado", type: "boolean")]
    public ?bool $estado;

    public function mapToDomainModel(): VoucherType
    {
        return new VoucherType(
            $this->codigo,
            $this->descripcion,
            $this->estado,

        );
    }

    public function mapFromDomainModel(VoucherType $vouchertype): VoucherTypeEntity
    {
        $this->codigo = $vouchertype->codigo;
        $this->descripcion = $vouchertype->descripcion;
        $this->estado = $vouchertype->estado;

        return $this;
    }
}