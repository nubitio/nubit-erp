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
#[Table(name: 'nota')]
class CreditNoteEntity
{
    public function __construct(
        #[Id]
        #[Column(name: 'nota_id', type: 'string')]
        public ?string $nota_id = null,
        #[Column(name: 'movimiento_id', type: 'string')]
        public ?string $movimiento_id = null,
        #[Column(name: 'fecha', type: 'date')]
        public ?\DateTime $fecha = null,
        #[Column(name: 'tipo_id', type: 'string')]
        public ?string $tipo_id = null,
        #[Column(name: 'codigo_motivo', type: 'string')]
        public ?string $codigo_motivo = null,
        #[Column(name: 'descripcion_motivo', type: 'string')]
        public ?string $descripcion_motivo = null,
        #[Column(name: 'serie', type: 'string')]
        public ?string $serie = null,
        #[Column(name: 'correlativo', type: 'integer')]
        public ?int $correlativo = null,
        #[Column(name: 'digest', type: 'string')]
        public ?string $digest = null,
        #[Column(name: 'envio_sunat', type: 'string')]
        public ?string $envio_sunat = null,
        #[Column(name: 'mensaje_sunat', type: 'string')]
        public ?string $mensaje_sunat = null,
        #[Column(name: 'created_by', type: 'integer')]
        public ?int $created_by = null,
        #[Column(name: 'updated_by', type: 'integer')]
        public ?int $updated_by = null,
        #[Column(name: 'created_at', type: 'datetime')]
        public ?\DateTime $created_at = null,
        #[Column(name: 'updated_at', type: 'datetime')]
        public ?\DateTime $updated_at = null,
    ) {
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

    public static function mapFromDomainModel(CreditNote $creditNote): CreditNoteEntity
    {
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
