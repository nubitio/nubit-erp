<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Write shape for `POST /api/restaurant/orders` (opening a table).
 */
final class OpenTableInput
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $tableId = 0;
}
