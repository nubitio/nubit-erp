<?php

namespace App\PaymentMethod\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class PaymentMethod
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('name')]
        public ?string $name = null,
        #[SerializedName('active')]
        public ?bool $active = false
    )
    {
    }
}