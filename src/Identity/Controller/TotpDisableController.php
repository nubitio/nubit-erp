<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use Nubit\AdminBundle\Identity\Exception\TotpException;
use Nubit\AdminBundle\Identity\TotpManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Same rule as the bundle's `DELETE /api/auth/totp` (a session alone cannot remove the second
 * factor: the current code or a recovery code is required), exposed as POST because the web
 * client cannot send a body with DELETE. Both endpoints stay available and equivalent.
 */
final class TotpDisableController extends AbstractController
{
    public function __construct(
        private readonly ActivityRecorderInterface $activity,
        // Only a service while nubit_admin.identity is enabled; optional so the container compiles with it off.
        private readonly ?TotpManager $totp = null,
    ) {}

    #[Route('/api/auth/totp/disable', name: 'api_auth_totp_disable', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($this->totp === null) {
            return $this->json(['message' => 'No disponible.'], Response::HTTP_NOT_FOUND);
        }
        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['message' => 'Authentication Required'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $code = is_scalar($payload['code'] ?? null) ? trim((string) $payload['code']) : '';
        $identifier = $user->getUserIdentifier();

        if ($this->totp->isEnrolled($identifier)) {
            try {
                $this->totp->verify($identifier, $code);
            } catch (TotpException $exception) {
                return $this->json(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }
        $this->totp->disable($identifier);
        $this->activity->record(ActivityType::TOTP_DISABLED, sprintf(
            '%s desactivó la verificación en dos pasos',
            $identifier,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
