<?php

declare(strict_types=1);

namespace App\Dto\Setup;

class SetupStatusDto
{
    public function __construct(
        public readonly bool $configured,
        public readonly int $userCount,
        public readonly int $companyCount,
        public readonly int $branchCount,
    ) {
    }
}
