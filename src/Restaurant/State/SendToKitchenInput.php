<?php

declare(strict_types=1);

namespace App\Restaurant\State;

/**
 * Write shape for `POST /api/restaurant/orders/{id}/send-to-kitchen`.
 * The target order comes from the URL; this action carries no body.
 */
final class SendToKitchenInput {}
