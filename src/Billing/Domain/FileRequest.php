<?php

namespace App\Billing\Domain;

use App\Billing\Model\Report\Parameters;
use App\Core\Domain\Annotation\IgnoreAutowire;
use Greenter\Model\DocumentInterface;

#[IgnoreAutowire]
readonly class FileRequest
{
    public function __construct(
        public DocumentInterface $document,
        public ?Parameters $parameters = null,
    ) {
    }
}
