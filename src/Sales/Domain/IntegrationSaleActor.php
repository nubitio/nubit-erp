<?php

declare(strict_types=1);

namespace App\Sales\Domain;

use Symfony\Component\Uid\Uuid;

/** Attribution supplied by a trusted adapter after authenticating its API client. */
final readonly class IntegrationSaleActor
{
    public string $clientId;

    public function __construct(string $clientId)
    {
        $this->clientId = Uuid::fromString($clientId)->toRfc4122();
    }
}
