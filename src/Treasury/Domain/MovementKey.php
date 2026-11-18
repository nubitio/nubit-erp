<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/**
 * Identity of a movement's business origin: the event, its id and which leg of it. It is the idempotency
 * key, so the same event recorded twice (an offline replay, a retry) yields one row.
 */
final readonly class MovementKey
{
    public function __construct(
        public MovementSource $source,
        public string $id,
        public Leg $leg = Leg::Main,
    ) {}
}
