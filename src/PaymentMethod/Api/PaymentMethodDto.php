<?php

namespace App\PaymentMethod\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\PaymentMethod\Domain\PaymentMethod;

#[ApiResource(
    shortName: 'PaymentMethod',
    provider: PaymentMethodProvider::class,
    processor: PaymentMethodProcessor::class,
)]
class PaymentMethodDto extends PaymentMethod
{
    #[ApiProperty(identifier: true)]
    public ?int $id;
}