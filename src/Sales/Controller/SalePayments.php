<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Sales\Entity\SaleRecord;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class SalePayments
{
    public function __invoke(SaleRecord $data): JsonResponse
    {
        return new JsonResponse($data->getPayments());
    }
}
