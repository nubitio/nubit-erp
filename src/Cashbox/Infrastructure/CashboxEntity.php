<?php

namespace App\Cashbox\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Cashbox\Domain\Cashbox;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "caja")]
class CashboxEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "caja_id", type: "string")]
    public ?string $caja_id;
    #[Column(name: "local_id", type: "string")]
    public ?string $local_id;
    #[Column(name: "apertura", type: "datetime")]
    public ?\DateTime $apertura;
    #[Column(name: "cierre", type: "datetime")]
    public ?\DateTime $cierre;
    #[Column(name: "usuario_id", type: "integer")]
    public ?int $usuario_id;
    #[Column(name: "estado", type: "boolean")]
    public ?bool $estado;

    public function mapToDomainModel(): Cashbox
    {
        return new Cashbox(
            $this->caja_id,
            $this->local_id,
            $this->apertura,
            $this->cierre,
            $this->usuario_id,
            $this->estado,

        );
    }

    public function mapFromDomainModel(Cashbox $cashbox): CashboxEntity
    {
        $this->caja_id = $cashbox->caja_id;
        $this->local_id = $cashbox->local_id;
        $this->apertura = $cashbox->apertura;
        $this->cierre = $cashbox->cierre;
        $this->usuario_id = $cashbox->usuario_id;
        $this->estado = $cashbox->estado;

        return $this;
    }
}