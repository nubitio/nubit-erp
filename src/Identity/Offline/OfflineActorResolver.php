<?php

declare(strict_types=1);

namespace App\Identity\Offline;

use App\Identity\Security\PermissionCheckerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the signature headers of an offline sale (`X-Offline-Credential`, `X-Offline-Signed-At`,
 * `X-Offline-Signature`) and says who made it.
 *
 * No `X-Offline-Credential` means an unsigned sale (a till without Web Crypto, or one queued
 * before signing existed): the caller falls back to the signed-in user. A credential that
 * is present must verify; a sale that claims a signature and fails it is refused.
 */
final readonly class OfflineActorResolver implements OfflineActors
{
    public function __construct(
        private OfflineCredentialService $credentials,
        private PermissionCheckerInterface $permissions,
    ) {}

    #[\Override]
    public function resolve(Request $request, string $idempotencyKey): ?OfflineActor
    {
        $credentialId = (string) $request->headers->get('X-Offline-Credential', '');
        if ($credentialId === '') {
            return null;
        }

        $signedAt = (string) $request->headers->get('X-Offline-Signed-At', '');
        $signature = (string) $request->headers->get('X-Offline-Signature', '');
        if (!ctype_digit($signedAt) || $signature === '') {
            throw new InvalidOfflineSignature('A la venta le faltan datos de su firma.');
        }

        $user = $this->credentials->verify(
            $credentialId,
            $idempotencyKey,
            (int) $signedAt,
            OfflineSignature::bodyHash($request->getContent()),
            $signature,
        );

        // What the person who signed may do, not what the session that delivers it may do.
        $granted = $this->permissions->permissionsFor($user);

        return new OfflineActor(
            (int) $user->getId(),
            static fn(string $permission): bool => (
                in_array('*', $granted, true) || in_array($permission, $granted, true)
            ),
        );
    }
}
