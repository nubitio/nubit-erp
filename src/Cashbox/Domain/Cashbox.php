<?php

namespace App\Cashbox\Domain;

use App\CashboxDetail\Domain\CashboxDetail;
use App\Domain\Annotation\IgnoreAutowire;
use App\Local\Domain\Local;
use App\User\Domain\User;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Cashbox
{
    public function __construct(
        #[SerializedName('caja_id')]
        public ?string $caja_id = null,
        #[SerializedName('local_id')]
        public ?string $local_id = null,
        #[SerializedName('apertura')]
        public ?\DateTime $apertura = null,
        #[SerializedName('cierre')]
        public ?\DateTime $cierre = null,
        #[SerializedName('usuario_id')]
        public ?int $usuario_id = null,
        #[SerializedName('estado')]
        public ?int $estado = null,
        public ?Local $local = null,
        public ?User $user = null,
        /**
         * @var CashboxDetail[] $items
         */
        public $items = [],
    ) {
    }

    public function getIncome(): float
    {
        $income = 0;
        foreach ($this->items as $item) {
            if (1 == $item->tipo_id) {
                $income += $item->monto;
            }
        }

        return $income;
    }

    public function getOutcome(): float
    {
        $outcome = 0;
        foreach ($this->items as $item) {
            if (2 == $item->tipo_id) {
                $outcome += $item->monto;
            }
        }

        return $outcome;
    }

    public function getBalance(): float
    {
        return $this->getIncome() - $this->getOutcome();
    }
}
