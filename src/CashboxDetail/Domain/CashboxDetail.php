<?php

namespace App\CashboxDetail\Domain;

use App\Category\Domain\Category;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Customer\Domain\Customer;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class CashboxDetail
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('caja_id')]
        public ?string $caja_id = null,
        #[SerializedName('uid')]
        public ?int $uid = null,
        #[SerializedName('tipo_id')]
        public ?int $tipo_id = null,
        public ?int $category_id = null,
        public ?Category $category = null,
        public ?int $employee_id = null,
        public ?Customer $employee = null,
        #[SerializedName('descripcion')]
        public ?string $descripcion = null,
        #[SerializedName('referencia')]
        public ?string $referencia = null,
        #[SerializedName('monto')]
        public ?float $monto = null,
    ) {
    }
}
