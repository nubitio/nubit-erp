<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

/** What reversing a collection did; `alreadyReversed` when the call found it undone and changed nothing. */
final readonly class ReversalResult
{
    public function __construct(
        public string $collectionId,
        public string $amount,
        public int $allocationsReversed,
        public bool $alreadyReversed,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'collectionId' => $this->collectionId,
            'status' => 'reversed',
            'amount' => $this->amount,
            'allocationsReversed' => $this->allocationsReversed,
            'alreadyReversed' => $this->alreadyReversed,
        ];
    }
}
