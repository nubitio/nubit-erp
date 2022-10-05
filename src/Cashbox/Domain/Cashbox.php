<?php

namespace App\Cashbox\Domain;

use App\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Cashbox
{
    public function __construct(
        #[SerializedName('caja_id')]
        public ?string $caja_id,
        #[SerializedName('local_id')]
        public ?string $local_id,
        #[SerializedName('apertura')]
        public ?\DateTime $apertura,
        #[SerializedName('cierre')]
        public ?\DateTime $cierre,
        #[SerializedName('usuario_id')]
        public ?int $usuario_id,
        #[SerializedName('estado')]
        public ?bool $estado,

    )
    {
    }
}