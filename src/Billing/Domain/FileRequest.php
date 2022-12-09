<?php

namespace App\Billing\Domain;

use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Report\Parameters;
use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class FileRequest
{
    public function __construct(
        public readonly Bill       $document,
        public readonly Parameters $parameters,
    )
    {
    }
}