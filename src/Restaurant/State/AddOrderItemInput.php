<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Write shape for `POST /api/restaurant/order-items`.
 */
final class AddOrderItemInput
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $orderId = 0;

    #[Assert\NotBlank]
    public string $productId = '';

    #[Assert\Positive]
    public int $qty = 1;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
