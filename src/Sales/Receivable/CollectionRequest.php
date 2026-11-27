<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Identity\Entity\User;
use DateTimeImmutable;

/** What a user asks when registering a collection. Independent of how the money is split among invoices. */
final readonly class CollectionRequest
{
    /**
     * @param non-empty-list<TenderInput> $tenders
     * @param list<array{installmentId: string, amount: string}> $allocations empty = oldest due date first
     * @param string $idempotencyKey stable id of the request, derived from the caller's Idempotency-Key
     */
    public function __construct(
        public int $customerId,
        public string $currency,
        public DateTimeImmutable $receivedOn,
        public array $tenders,
        public array $allocations,
        public string $idempotencyKey,
        public User $createdBy,
    ) {}
}
