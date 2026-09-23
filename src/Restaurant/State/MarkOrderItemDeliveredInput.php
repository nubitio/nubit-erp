<?php

declare(strict_types=1);

namespace App\Restaurant\State;

/**
 * Write shape for `POST /api/restaurant/order-items/{id}/delivered`.
 * The target item comes from the URL; this action carries no body.
 */
final class MarkOrderItemDeliveredInput {}
