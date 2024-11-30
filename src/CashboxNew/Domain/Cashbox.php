<?php

namespace App\CashboxNew\Domain;

use App\Branch\Domain\Branch;
use App\CashboxNewDetail\Domain\CashboxDetail;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\User\Domain\User;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Cashbox
{
    public function __construct(
        #[SerializedName('caja_id')]
        public ?string $caja_id = null,
        #[SerializedName('local')]
        public ?Branch $local = null,
        #[SerializedName('apertura')]
        public ?\DateTimeInterface $apertura = null,
        #[SerializedName('cierre')]
        public ?\DateTimeInterface $cierre = null,
        #[SerializedName('usuario')]
        public ?User $usuario = null,
        #[SerializedName('estado')]
        public ?int $estado = null,
        /** @var CashboxDetail[] $details */
        #[SerializedName('details')]
        public array $details = [],
    ) {
    }

    public function getIncome(): float
    {
        $income = 0.0;
        foreach ($this->details as $detail) {
            if (1 === $detail->tipo_id) {
                $income += $detail->monto;
            }
        }

        return $income;
    }

    public function getExpense(): float
    {
        $expense = 0.0;
        foreach ($this->details as $detail) {
            if (2 === $detail->tipo_id) {
                $expense += $detail->monto;
            }
        }

        return $expense;
    }

    public function getBalance(): float
    {
        $balance = 0.0;
        foreach ($this->details as $detail) {
            if (1 === $detail->tipo_id) {
                $balance += $detail->monto;
            } else {
                $balance -= $detail->monto;
            }
        }

        return $balance;
    }
}
