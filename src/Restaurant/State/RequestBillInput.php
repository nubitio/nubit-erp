<?php

declare(strict_types=1);

namespace App\Restaurant\State;

/**
 * Write shape for `POST /api/restaurant/orders/{id}/request-bill`.
 * The target order comes from the URL; this action carries no body.
 */
final class RequestBillInput {}
