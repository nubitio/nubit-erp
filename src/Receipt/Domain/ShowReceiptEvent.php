<?php

namespace App\Receipt\Domain;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ShowReceiptEvent extends RequestEvent
{
    public const string NAME = 'receipt.show';

    private Receipt $receipt;

    public function __construct(HttpKernelInterface $kernel, Request $request, int $requestType, Receipt $receipt)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setReceipt($receipt);
    }

    public function getReceipt(): Receipt
    {
        return $this->receipt;
    }

    public function setReceipt(Receipt $receipt): self
    {
        $this->receipt = $receipt;

        return $this;
    }
}
