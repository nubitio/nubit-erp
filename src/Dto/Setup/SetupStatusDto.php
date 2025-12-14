<?php

declare(strict_types=1);

namespace App\Dto\Setup;

readonly class SetupStatusDto
{
    public function __construct(
        public bool $configured,
        public int $userCount,
        public int $companyCount,
        public int $branchCount,
    ) {
    }
}
