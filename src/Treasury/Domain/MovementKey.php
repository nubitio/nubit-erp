<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

use Symfony\Component\Uid\Uuid;

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

    /**
     * Deterministic uuid of this key, used as the idempotency key in the drawer ledger (`cash_movements`),
     * which has no source columns of its own. The same key always gives the same uuid.
     */
    public function asUuid(): string
    {
        return Uuid::v5(
            Uuid::fromString(Uuid::NAMESPACE_URL),
            sprintf('urn:nubit:treasury:%s:%s:%s', $this->source->value, $this->id, $this->leg->value),
        )->toRfc4122();
    }
}
