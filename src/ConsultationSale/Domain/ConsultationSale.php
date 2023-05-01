<?php

namespace App\ConsultationSale\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class ConsultationSale
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('consultation_id')]
        public ?int $consultation_id = null,
        #[SerializedName('sale_id')]
        public ?int $sale_id = null
    )
    {
    }
}