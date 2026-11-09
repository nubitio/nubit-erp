<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use App\Identity\Invitation\InvitationMessages;
use App\Identity\Invitation\PendingInvitation;
use App\Identity\Invitation\PendingInvitations;
use Nubit\AdminBundle\Identity\Exception\IdentityException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pending invitations: list them, issue a fresh link (the previous one stops working) or cancel them.
 * Creating an invitation is {@see UserInvitationController}.
 */
final class PendingInvitationController extends AbstractController
{
    public function __construct(
        private readonly PendingInvitations $pending,
        private readonly ActivityRecorderInterface $activity,
    ) {}

    #[Route('/api/user-invitations', name: 'api_user_invitation_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:users.view');
        if (!$this->pending->enabled()) {
            return $this->disabled();
        }

        return $this->json(['items' => array_map(
            static fn(PendingInvitation $invitation): array => $invitation->toArray(),
            $this->pending->all(),
        )]);
    }

    #[Route('/api/user-invitations/resend', name: 'api_user_invitation_resend', methods: ['POST'])]
    public function resend(Request $request): JsonResponse
    {
        $found = $this->manageable($request);
        if ($found instanceof JsonResponse) {
            return $found;
        }

        $caller = $this->getUser();
        try {
            $record = $this->pending->resend($found, $caller instanceof User ? $caller->getUserIdentifier() : null);
        } catch (IdentityException $exception) {
            return $this->json([
                'message' => InvitationMessages::translate($exception->getMessage()),
            ], Response::HTTP_CONFLICT);
        }

        $this->report(ActivityType::INVITATION_RESENT, 'reenvió', $found);

        return $this->json(['email' => $found->email(), 'expiresAt' => $record->getExpiresAt()->format(\DATE_ATOM)]);
    }

    #[Route('/api/user-invitations/revoke', name: 'api_user_invitation_revoke', methods: ['POST'])]
    public function revoke(Request $request): JsonResponse
    {
        $found = $this->manageable($request);
        if ($found instanceof JsonResponse) {
            return $found;
        }

        $this->pending->revoke($found);
        $this->report(ActivityType::INVITATION_REVOKED, 'revocó', $found);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** The invitation named in the body, or the error response to return instead. */
    private function manageable(Request $request): PendingInvitation|JsonResponse
    {
        $this->denyAccessUnlessGranted('perm:users.create');
        if (!$this->pending->enabled()) {
            return $this->disabled();
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $found = $this->pending->find(is_scalar($payload['email'] ?? null) ? (string) $payload['email'] : '');
        if ($found === null) {
            return $this->json(['message' => 'Esa invitación ya no está pendiente.'], Response::HTTP_NOT_FOUND);
        }
        if ($found->requiresSuperAdmin() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json([
                'message' => 'Solo un super administrador puede gestionar esa invitación.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $found;
    }

    private function report(string $type, string $verb, PendingInvitation $invitation): void
    {
        $caller = $this->getUser();
        $this->activity->record(
            $type,
            sprintf(
                '%s %s la invitación de %s',
                $caller instanceof User ? $caller->getUsername() : 'Un administrador',
                $verb,
                $invitation->email(),
            ),
            subject: $invitation->email(),
        );
    }

    private function disabled(): JsonResponse
    {
        return $this->json(['message' => 'Las invitaciones no están habilitadas.'], Response::HTTP_NOT_FOUND);
    }
}
