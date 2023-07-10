<?php

namespace App\Transfer\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Transfer
{
    public function __construct(
        #[SerializedName('transfer_id')]
        public ?string $transfer_id = null,
        #[SerializedName('fecha')]
        public ?\DateTimeInterface $fecha = null,
        #[SerializedName('almacen_origen')]
        public ?string $almacen_origen = null,
        #[SerializedName('almacen_destino')]
        public ?string $almacen_destino = null,
        #[SerializedName('nota')]
        public ?string $nota = null,
        #[SerializedName('salida_id')]
        public ?string $salida_id = null,
        #[SerializedName('ingreso_id')]
        public ?string $ingreso_id = null,
        #[SerializedName('created_by')]
        public ?int $created_by = null,
        #[SerializedName('updated_by')]
        public ?int $updated_by = null,
        #[SerializedName('created_at')]
        public ?\DateTimeInterface $created_at = null,
        #[SerializedName('updated_at')]
        public ?\DateTimeInterface $updated_at = null
    ) {
    }
}
