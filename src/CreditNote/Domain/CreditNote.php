<?php

namespace App\CreditNote\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class CreditNote
{
    public function __construct(
        #[SerializedName('nota_id')]
        public ?string $nota_id,
        #[SerializedName('movimiento_id')]
        public ?string $movimiento_id,
        #[SerializedName('fecha')]
        public ?\DateTime $fecha,
        #[SerializedName('tipo_id')]
        public ?string $tipo_id,
        #[SerializedName('codigo_motivo')]
        public ?string $codigo_motivo,
        #[SerializedName('descripcion_motivo')]
        public ?string $descripcion_motivo,
        #[SerializedName('serie')]
        public ?string $serie,
        #[SerializedName('correlativo')]
        public ?int $correlativo,
        #[SerializedName('digest')]
        public ?string $digest,
        #[SerializedName('envio_sunat')]
        public ?string $envio_sunat,
        #[SerializedName('mensaje_sunat')]
        public ?string $mensaje_sunat,
        #[SerializedName('created_by')]
        public ?int $created_by,
        #[SerializedName('updated_by')]
        public ?int $updated_by,
        #[SerializedName('created_at')]
        public ?\DateTime $created_at,
        #[SerializedName('updated_at')]
        public ?\DateTime $updated_at,

    )
    {
    }
}