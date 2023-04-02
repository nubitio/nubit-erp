<?php

namespace App\CashboxDetail\Infrastructure;

use App\Cashbox\Infrastructure\CashboxEntity;
use App\CashboxDetail\Domain\CashboxDetail;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: 'caja_detalle')]
class CashboxDetailEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: 'id', type: 'integer')]
    public ?int $id;
    #[Column(name: 'uid', type: 'integer')]
    public ?int $uid;
    #[Column(name: 'caja_id', type: 'string')]
    public ?string $caja_id;
    #[Column(name: 'tipo_id', type: 'integer')]
    public ?int $tipo_id;
    #[Column(name: 'descripcion', type: 'string')]
    public ?string $descripcion;
    #[Column(name: 'referencia', type: 'string')]
    public ?string $referencia;
    #[Column(name: 'monto', type: 'decimal')]
    public ?float $monto;
    #[ManyToOne(targetEntity: CashboxEntity::class, inversedBy: 'items')]
    #[JoinColumn(name: 'caja_id', referencedColumnName: 'caja_id')]
    public CashboxEntity $cashbox;

    public function mapToDomainModel(): CashboxDetail
    {
        return new CashboxDetail(
            $this->id,
            $this->uid,
            $this->caja_id,
            $this->tipo_id,
            $this->descripcion,
            $this->referencia,
            $this->monto,
        );
    }

    public function mapFromDomainModel(CashboxDetail $cashboxDetail): CashboxDetailEntity
    {
        $this->id = $cashboxDetail->id;
        $this->uid = $cashboxDetail->uid ?? $this->uid;
        $this->caja_id = $cashboxDetail->caja_id;
        $this->tipo_id = $cashboxDetail->tipo_id;
        $this->descripcion = $cashboxDetail->descripcion;
        $this->referencia = $cashboxDetail->referencia;
        $this->monto = $cashboxDetail->monto;

        return $this;
    }
}
