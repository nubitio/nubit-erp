<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Billing\Sunat\Model\Report\Parameters;
use App\Efact\Annotation\IgnoreAutowire;
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
