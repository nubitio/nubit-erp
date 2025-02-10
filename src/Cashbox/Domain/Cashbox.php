<?php

namespace App\Cashbox\Domain;

use App\CashboxDetail\Domain\CashboxDetail;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\User\Domain\User;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Cashbox
{
    public function __construct(
        #[SerializedName('caja_id')]
        public ?string $caja_id = null,
        #[SerializedName('apertura')]
        public ?\DateTimeInterface $apertura = null,
        #[SerializedName('cierre')]
        public ?\DateTimeInterface $cierre = null,
        #[SerializedName('balance')]
        public ?float $balance = null,
        #[SerializedName('usuario')]
        public ?User $usuario = null,
        #[SerializedName('estado')]
        public ?int $estado = null,
        /** @var CashboxDetail[] $details */
        #[SerializedName('details')]
        public array $details = [],
    ) {
    }
}
