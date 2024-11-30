<?php

namespace App\Performance\Domain;

use App\ComboOption\Domain\ComboOption;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Performance
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('lines')]
        public ?ComboOption $lines = null,
        #[SerializedName('products')]
        public ?Product $products = null,
        #[SerializedName('types')]
        public ?ComboOption $types = null,
        #[SerializedName('date')]
        public ?\DateTimeInterface $date = null,
        #[SerializedName('start')]
        public ?\DateTimeInterface $start = null,
        #[SerializedName('end')]
        public ?\DateTimeInterface $end = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
        #[SerializedName('obs')]
        public ?string $obs = null,
    ) {
    }

    #[SerializedName('nominal')]
    public function getProdNominal(): int
    {
        $value = intval($this->types?->_value);

        return match ($value) {
            220 => 13600,
            400, 450 => 7000,
            620 => 4500,
            650 => 5385,
            2 => 2500,
            3 => 1800,
            default => 0,
        };
    }

    #[SerializedName('real')]
    public function getProdReal(): float
    {
        $result = 0;
        if (0 != $this->getNumericWorkedTime() * 24) {
            $result = $this->getProdRealOpt();
            $result *= $this->quantity / ($this->getNumericWorkedTime() * 24);
        }

        return round($result);
    }

    #[SerializedName('efficiency')]
    public function getEfficiency(): float
    {
        $efficiency = 0;
        $real = $this->getProdReal();
        $pNom = $this->getProdNominal();
        if (0 != $this->getProdReal()) {
            $efficiency = $real / $pNom;
            $efficiency = round($efficiency, 2) * 100;
        }

        return $efficiency;
    }

    #[SerializedName('workedTime')]
    public function getWorkedTime(): string
    {
        if (null === $this->start || null === $this->end) {
            return '00:00';
        }
        $diff = $this->start->diff($this->end);

        return $diff->format('%H:%I');
    }

    private function getNumericWorkedTime(): float
    {
        if (null === $this->start || null === $this->end) {
            return 0;
        }
        $diff = $this->start->diff($this->end);

        return ($diff->h / 24) + ($diff->i / (24 * 60));
    }

    private function getProdRealOpt(): int
    {
        $value = intval($this->types?->_value);

        return match ($value) {
            220 => 18180,
            400 => 10000,
            450 => 8888,
            620 => 6450,
            650 => 6155,
            2 => 2000,
            3 => 1333,
            default => 0,
        };
    }
}
