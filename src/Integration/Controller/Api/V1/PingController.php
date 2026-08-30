<?php

declare(strict_types=1);

namespace App\Integration\Controller\Api\V1;

use App\Integration\Security\ApiClientUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liveness + credential check for the integration API. Confirms a key
 * authenticates, resolves to a tenant, and reports its scopes.
 */
final class PingController extends AbstractController
{
    #[Route('/api/v1/ping', name: 'integration_v1_ping', methods: ['GET'])]
    #[IsGranted('ROLE_INTEGRATION')]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof ApiClientUser, 'the integration firewall guarantees an ApiClientUser');

        return new JsonResponse([
            'ok' => true,
            'client' => $user->getClientName(),
            'tenant' => $user->getTenant(),
            'scopes' => $user->getScopes(),
            'api_version' => 'v1',
        ]);
    }
}
