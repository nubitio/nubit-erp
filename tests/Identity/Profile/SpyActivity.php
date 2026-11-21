<?php

declare(strict_types=1);

namespace App\Tests\Identity\Profile;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Entity\User;

final class SpyActivity implements ActivityRecorderInterface
{
    /** @var list<string> */
    public array $types = [];

    public function record(
        string $type,
        string $summary,
        ?string $detail = null,
        ?string $subject = null,
        User|string|null $actor = null,
    ): void {
        $this->types[] = $type;
    }
}
