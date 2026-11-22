<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Offline\OfflineCredentialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/pos/offline-credential — the key this user's till signs offline sales with.
 *
 * Only a user who may synchronize offline sales gets one, and the answer is for that user's
 * own session: it is the secret that lets a sale be attributed to them.
 */
final class OfflineCredentialController extends AbstractController
{
    public function __construct(
        private readonly OfflineCredentialService $credentials,
    ) {}

    #[Route('/api/pos/offline-credential', name: 'pos_offline_credential', methods: ['POST'])]
    #[IsGranted('perm:pos.offline_sale')]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $this->json($this->credentials->issueFor($user)->toArray());
    }
}
