<?php

namespace App\Orden\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Mesa\Domain\Mesa;
use App\OrdenDetail\Domain\OrdenDetail;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Orden
{
    public function __construct(
        #[SerializedName('orden_id')]
        public ?int $orden_id = null,
        #[SerializedName('fecha')]
        public ?\DateTimeInterface $fecha = null,
        #[SerializedName('mesa')]
        public ?Mesa $mesa = null,
        #[SerializedName('mesa_origen')]
        public ?int $mesa_origen = null,
        #[SerializedName('nota')]
        public ?string $nota = null,
        #[SerializedName('estado_cocina')]
        public ?int $estado_cocina = null,
        #[SerializedName('estado_barra')]
        public ?int $estado_barra = null,
        #[SerializedName('usuario_id')]
        public ?int $usuario_id = null,
        #[SerializedName('glosa')]
        public ?string $glosa = null,
        #[SerializedName('estado')]
        public ?int $estado = null,
        #[SerializedName('anulado')]
        public ?bool $anulado = false,
        #[SerializedName('date_created')]
        public ?\DateTimeInterface $date_created = null,
        #[SerializedName('date_updated')]
        public ?\DateTimeInterface $date_updated = null,
        #[SerializedName('user_create')]
        public ?int $user_create = null,
        #[SerializedName('user_update')]
        public ?int $user_update = null,
        /** @var OrdenDetail[] $details */
        #[SerializedName('details')]
        public array $details = []
    ) {
    }
}
