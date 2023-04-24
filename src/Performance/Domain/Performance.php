<?php

namespace App\Performance\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class Performance
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('line_fk')]
        public ?int $line_fk = null,
        #[SerializedName('product_fk')]
        public ?string $product_fk = null,
        #[SerializedName('type_fk')]
        public ?int $type_fk = null,
        #[SerializedName('date')]
        public ?\DateTimeInterface $date = null,
        #[SerializedName('start')]
        public ?\DateTimeInterface $start = null,
        #[SerializedName('end')]
        public ?\DateTimeInterface $end = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
        #[SerializedName('obs')]
        public ?string $obs = null
    )
    {
    }
}