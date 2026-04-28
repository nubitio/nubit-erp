<?php

declare(strict_types=1);

namespace App\Setup\Service;

interface E2eTestSeedServiceInterface
{
    /**
     * @return array{username: string, password: string, saleId: string}
     */
    public function seed(): array;

    public function reset(): void;
}
