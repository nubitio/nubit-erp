<?php

namespace App\Core\Domain\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class TenantStamp implements StampInterface
{
    public function __construct(
        public string $tenant
    ) {
    }
}
