<?php

namespace App\Domain\CashBox;

class GetInfoReceiptType
{
    private ICashBoxRepository $cashBoxRepository;

    public function __construct(ICashBoxRepository $cashBoxRepository)
    {
        $this->cashBoxRepository = $cashBoxRepository;
    }

    public function execute(string $id): array
    {
        return $this->cashBoxRepository->getInfoReceiptType($id);
    }
}
