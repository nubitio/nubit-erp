<?php

declare(strict_types=1);

namespace App\Identity\Offline;

use Symfony\Component\HttpFoundation\Request;

/** Says who made an offline sale, from the signature it carries. */
interface OfflineActors
{
    /**
     * @return OfflineActor|null null when the sale carries no signature
     *
     * @throws InvalidOfflineSignature when it claims one that does not hold
     */
    public function resolve(Request $request, string $idempotencyKey): ?OfflineActor;
}
