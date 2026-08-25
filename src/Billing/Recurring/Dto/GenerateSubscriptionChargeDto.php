<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Dto;

final class GenerateSubscriptionChargeDto
{
    public function __construct(public string $id) {}
}
