<?php

namespace App\CreditNote\Infrastructure;

use App\CreditNote\Domain\CreditNote;
use App\Domain\Annotation\IgnoreAutowire;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[IgnoreAutowire]
#[Entity]
#[Table(name: "nota")]
class CreditNoteEntity
{
    public function __construct(
        #[Id]
        #[Column(name: "nota_id", type: "string")]
        public ?string    $nota_id,
        #[Column(name: "movimiento_id", type: "string")]
        public ?string    $movimiento_id,
        #[Column(name: "fecha", type: "date")]
        public ?\DateTime $fecha,
        #[Column(name: "tipo_id", type: "string")]
        public ?string    $tipo_id,
        #[Column(name: "codigo_motivo", type: "string")]
        public ?string    $codigo_motivo,
        #[Column(name: "descripcion_motivo", type: "string")]
        public ?string    $descripcion_motivo,
        #[Column(name: "serie", type: "string")]
        public ?string    $serie,
        #[Column(name: "correlativo", type: "integer")]
        public ?int       $correlativo,
        #[Column(name: "digest", type: "string")]
        public ?string    $digest,
        #[Column(name: "envio_sunat", type: "string")]
        public ?string    $envio_sunat,
        #[Column(name: "mensaje_sunat", type: "string")]
        public ?string    $mensaje_sunat,
        #[Column(name: "created_by", type: "integer")]
        public ?int       $created_by,
        #[Column(name: "updated_by", type: "integer")]
        public ?int       $updated_by,
        #[Column(name: "created_at", type: "datetime")]
        public ?\DateTime $created_at,
        #[Column(name: "updated_at", type: "datetime")]
        public ?\DateTime $updated_at,
    )
    {
    }

    public function mapToDomainModel(): CreditNote
    {
        return new CreditNote(
            $this->nota_id,
            $this->movimiento_id,
            $this->fecha,
            $this->tipo_id,
            $this->codigo_motivo,
            $this->descripcion_motivo,
            $this->serie,
            $this->correlativo,
            $this->digest,
            $this->envio_sunat,
            $this->mensaje_sunat,
            $this->created_by,
            $this->updated_by,
            $this->created_at,
            $this->updated_at,

        );
    }

    public static function mapFromDomainModel(CreditNote $creditNote, CreditNoteEntity $entity = null): CreditNoteEntity
    {
        if ($entity) {
            $entity->nota_id = $creditNote->nota_id ?? $entity->nota_id;
            $entity->movimiento_id = $creditNote->movimiento_id ?? $entity->movimiento_id;
            $entity->fecha = $creditNote->fecha ?? $entity->fecha;
            $entity->tipo_id = $creditNote->tipo_id ?? $entity->tipo_id;
            $entity->codigo_motivo = $creditNote->codigo_motivo ?? $entity->codigo_motivo;
            $entity->descripcion_motivo = $creditNote->descripcion_motivo ?? $entity->descripcion_motivo;
            $entity->serie = $creditNote->serie ?? $entity->serie;
            $entity->correlativo = $creditNote->correlativo ?? $entity->correlativo;
            $entity->digest = $creditNote->digest ?? $entity->digest;
            $entity->envio_sunat = $creditNote->envio_sunat ?? $entity->envio_sunat;
            $entity->mensaje_sunat = $creditNote->mensaje_sunat ?? $entity->mensaje_sunat;
            $entity->created_by = $creditNote->created_by ?? $entity->created_by;
            $entity->updated_by = $creditNote->updated_by ?? $entity->updated_by;
            $entity->created_at = $creditNote->created_at ?? $entity->created_at;
            $entity->updated_at = $creditNote->updated_at ?? $entity->updated_at;

            return $entity;
        }

        return new self(
            $creditNote->nota_id,
            $creditNote->movimiento_id,
            $creditNote->fecha,
            $creditNote->tipo_id,
            $creditNote->codigo_motivo,
            $creditNote->descripcion_motivo,
            $creditNote->serie,
            $creditNote->correlativo,
            $creditNote->digest,
            $creditNote->envio_sunat,
            $creditNote->mensaje_sunat,
            $creditNote->created_by,
            $creditNote->updated_by,
            $creditNote->created_at,
            $creditNote->updated_at,
        );
    }
}