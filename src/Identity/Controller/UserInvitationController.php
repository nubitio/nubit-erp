<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Identity\Exception\IdentityException;
use Nubit\AdminBundle\Identity\InvitationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administrator side of invitations, wrapping the bundle's {@see InvitationService}.
 *
 * The bundle's own POST /api/invitations takes Symfony role names and checks them against the
 * caller's roles, which only covers this ERP's three legacy roles. Here a role is an entity, so
 * the invitation carries `role:<id>` and authority is checked the way the Role API does it.
 * The token is deliberately NOT returned: it travels only in the e-mail.
 */
final class UserInvitationController extends AbstractController
{
    private const int SUPER_ADMIN_ROLE_ID = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        // Only a service while nubit_admin.identity is enabled; must stay optional or the
        // container would fail to compile with the module off.
        private readonly ?InvitationService $invitations = null,
    ) {}

    #[Route('/api/user-invitations', name: 'api_user_invitation_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:users.create');
        if ($this->invitations === null) {
            return $this->json(['message' => 'Las invitaciones no están habilitadas.'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $role = $this->entityManager->find(Role::class, (int) ($payload['roleId'] ?? 0));
        if (!$role instanceof Role) {
            return $this->json(['message' => 'Elige un rol para el usuario.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // Same rule as editing roles: only a super administrator may hand out the super administrator role.
        if ($role->getId() === self::SUPER_ADMIN_ROLE_ID && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json([
                'message' => 'Solo un super administrador puede invitar con ese rol.',
            ], Response::HTTP_FORBIDDEN);
        }

        $caller = $this->getUser();

        try {
            $issued = $this->invitations->invite(
                (string) ($payload['email'] ?? ''),
                ['role:' . (int) $role->getId()],
                $caller instanceof User ? $caller->getUserIdentifier() : null,
            );
        } catch (IdentityException $exception) {
            return $this->json(['message' => $this->translate($exception->getMessage())], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'email' => $issued['record']->getSubject(),
            'role' => $role->getName(),
            'expiresAt' => $issued['record']->getExpiresAt()->format(\DATE_ATOM),
        ], Response::HTTP_CREATED);
    }

    private function translate(string $message): string
    {
        return match (true) {
            str_contains($message, 'valid email') => 'Escribe un correo válido.',
            str_contains($message, 'already has an account') => 'Ese correo ya tiene una cuenta.',
            default => $message,
        };
    }
}
