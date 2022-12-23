<?php

namespace App\Transfer\Infrastructure;

use App\Domain\Annotation\IgnoreAutowire;
use App\Transfer\Domain\Transfer;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "transfer")]
class TransferEntity
{
    #[Id]
    #[Column(name: "transfer_id", type: "string")]
    public ?string $transfer_id;
    #[Column(name: "fecha", type: "date")]
    public ?\DateTime $fecha;
    #[Column(name: "almacen_origen", type: "string")]
    public ?string $almacen_origen;
    #[Column(name: "almacen_destino", type: "string")]
    public ?string $almacen_destino;
    #[Column(name: "nota", type: "string")]
    public ?string $nota;
    #[Column(name: "salida_id", type: "string")]
    public ?string $salida_id;
    #[Column(name: "ingreso_id", type: "string")]
    public ?string $ingreso_id;
    #[Column(name: "created_by", type: "integer")]
    public ?int $created_by;
    #[Column(name: "updated_by", type: "integer")]
    public ?int $updated_by;
    #[Column(name: "created_at", type: "datetime")]
    public ?\DateTime $created_at;
    #[Column(name: "updated_at", type: "datetime")]
    public ?\DateTime $updated_at;

    public function mapToDomainModel(): Transfer
    {
        return new Transfer(
            $this->transfer_id,
            $this->fecha,
            $this->almacen_origen,
            $this->almacen_destino,
            $this->nota,
            $this->salida_id,
            $this->ingreso_id,
            $this->created_by,
            $this->updated_by,
            $this->created_at,
            $this->updated_at,
        );
    }

    public function mapFromDomainModel(Transfer $transfer): TransferEntity
    {
        $this->transfer_id = $transfer->transfer_id;
        $this->fecha = $transfer->fecha;
        $this->almacen_origen = $transfer->almacen_origen;
        $this->almacen_destino = $transfer->almacen_destino;
        $this->nota = $transfer->nota;
        $this->salida_id = $transfer->salida_id;
        $this->ingreso_id = $transfer->ingreso_id;
        $this->created_by = $transfer->created_by;
        $this->updated_by = $transfer->updated_by;
        $this->created_at = $transfer->created_at;
        $this->updated_at = $transfer->updated_at;

        return $this;
    }
}