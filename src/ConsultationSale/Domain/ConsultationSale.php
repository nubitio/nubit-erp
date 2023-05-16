<?php

namespace App\ConsultationSale\Domain;

use App\Consultation\Domain\Consultation;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Sale\Domain\Sale;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class ConsultationSale
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('consultation')]
        public ?Consultation $consultation = null,
        #[SerializedName('sale')]
        public ?Sale $sale = null
    ) {
    }
}
