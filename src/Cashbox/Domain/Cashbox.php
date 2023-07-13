<?php

namespace App\Cashbox\Domain;

use App\Branch\Domain\Branch;
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
        public ?Branch $local = null,
        public ?User $user = null,
        /**
         * @var CashboxDetail[] $details
         */
        public array $details = [],
    ) {
    }

    public function getIncome(): float
    {
        $income = 0;
        foreach ($this->details as $detail) {
            if (1 == $detail->tipo_id) {
                $income += $detail->monto;
            }
        }

        return $income;
    }

    public function getOutcome(): float
    {
        $outcome = 0;
        foreach ($this->details as $detail) {
            if (2 == $detail->tipo_id) {
                $outcome += $detail->monto;
            }
        }

        return $outcome;
    }

    public function getBalance(): float
    {
        return $this->getIncome() - $this->getOutcome();
    }
}
