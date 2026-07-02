<?php

declare(strict_types=1);

namespace App\Setup\Dto;

readonly class SetupStatusDto
{
    public function __construct(
        public bool $configured,
        public int $userCount,
        public int $companyCount,
        public int $branchCount,
    ) {}
}
