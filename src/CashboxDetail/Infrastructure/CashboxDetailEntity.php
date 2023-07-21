<?php

namespace App\CashboxDetail\Infrastructure;

use App\Cashbox\Infrastructure\CashboxEntity;
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
    #[ManyToOne(targetEntity: CashboxEntity::class, inversedBy: 'details')]
    #[JoinColumn(name: 'caja_id', referencedColumnName: 'caja_id')]
    public ?CashboxEntity $cashbox;
}
