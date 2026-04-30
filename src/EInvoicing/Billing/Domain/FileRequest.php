<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Domain;

use App\EInvoicing\Billing\Model\Report\Parameters;
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
