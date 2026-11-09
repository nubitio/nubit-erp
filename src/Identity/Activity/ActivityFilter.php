<?php

declare(strict_types=1);

namespace App\Identity\Activity;

use DateTimeImmutable;

/** Immutable filter for the activity record. Bounds are UTC instants. */
final readonly class ActivityFilter
{
    public function __construct(
        /** "sensitive", "all" or one of {@see ActivityType::GROUPS}. */
        public string $type = 'sensitive',
        public ?string $actor = null,
        public ?DateTimeImmutable $fromUtc = null,
        public ?DateTimeImmutable $toUtc = null,
        public ?string $search = null,
    ) {}
}
