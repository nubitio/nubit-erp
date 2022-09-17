<?php

namespace App\Transaction\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Transaction\Domain\Transaction;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "operacion")]
class TransactionEntity
{
    #[Id]
    #[GeneratedValue]
    #[Column(name: "operacion_id", type: "string")]
    public ?string $operacion_id;
    #[Column(name: "descripcion", type: "string")]
    public ?string $descripcion;
    #[Column(name: "tipo_id", type: "integer")]
    public ?int $tipo_id;
    #[Column(name: "estado", type: "boolean")]
    public ?bool $estado;

    public function mapToDomainModel(): Transaction
    {
        return new Transaction(
            $this->operacion_id,
            $this->descripcion,
            $this->tipo_id,
            $this->estado,

        );
    }

    public function mapFromDomainModel(Transaction $transaction): TransactionEntity
    {
        $this->operacion_id = $transaction->operacion_id;
        $this->descripcion = $transaction->descripcion;
        $this->tipo_id = $transaction->tipo_id;
        $this->estado = $transaction->estado;

        return $this;
    }
}