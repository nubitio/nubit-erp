<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Billing\Model\Report\Parameters;
use App\Core\Annotation\IgnoreAutowire;
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
