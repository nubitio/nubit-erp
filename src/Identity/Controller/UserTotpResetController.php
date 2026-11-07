<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Efact\Audit\AuditLogger;
use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Identity\TotpManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * An administrator removes the second factor of a colleague who lost their device.
 * The person keeps their password and can enrol a new authenticator.
 */
final class UserTotpResetController extends AbstractController
{
    private const int SUPER_ADMIN_ROLE_ID = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $audit,
        private readonly ActivityRecorderInterface $activity,
        // Only a service while nubit_admin.identity is enabled; optional so the container compiles with it off.
        private readonly ?TotpManager $totp = null,
    ) {}

    #[Route('/api/users/{id<\d+>}/totp', name: 'api_user_totp_reset', methods: ['DELETE'])]
    public function __invoke(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:users.edit');
        if ($this->totp === null) {
            return $this->json([
                'message' => 'La verificación en dos pasos no está habilitada.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->entityManager->find(User::class, $id);
        if (!$user instanceof User || $user->getDeletedAt() !== null) {
            return $this->json(['message' => 'Usuario no encontrado.'], Response::HTTP_NOT_FOUND);
        }
        // Resetting a super administrator's factor is as sensitive as editing their role.
        if ($user->getRole()?->getId() === self::SUPER_ADMIN_ROLE_ID && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json([
                'message' => 'Solo un super administrador puede restablecer a otro.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->totp->disable($user->getUserIdentifier());
        $this->audit->log('auth.totp_reset', ['target' => $user->getUserIdentifier()]);
        $caller = $this->getUser();
        $this->activity->record(
            ActivityType::TOTP_RESET,
            sprintf(
                '%s restableció la verificación en dos pasos de %s',
                $caller instanceof User ? $caller->getUsername() : 'Un administrador',
                $user->getUsername(),
            ),
            subject: $user->getUsername(),
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
