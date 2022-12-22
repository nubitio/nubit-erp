<?php

namespace App\Transfer\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Transfer
{
    public function __construct(
        #[SerializedName('transfer_id')]
        public ?string $transfer_id,
        #[SerializedName('fecha')]
        public ?\DateTime $fecha,
        #[SerializedName('almacen_origen')]
        public ?string $almacen_origen,
        #[SerializedName('almacen_destino')]
        public ?string $almacen_destino,
        #[SerializedName('nota')]
        public ?string $nota,
        #[SerializedName('salida_id')]
        public ?string $salida_id,
        #[SerializedName('ingreso_id')]
        public ?string $ingreso_id,
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