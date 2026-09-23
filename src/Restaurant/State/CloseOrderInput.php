<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Write shape for `POST /api/restaurant/orders/{id}/close`.
 *
 * `saleId` is the id already returned by `POST /api/sales` — the frontend
 * creates the sale first (same call the web POS makes) and only then closes
 * the table, so this context never talks to Sales in PHP.
 */
final class CloseOrderInput
{
    #[Assert\NotBlank]
    public string $saleId = '';
}
