<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/api/control-plane/session')]
final class SessionApiController extends AbstractController
{
    #[Route('', name: 'api_control_plane_session', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'user' => $user?->getUserIdentifier(),
            'roles' => $user?->getRoles() ?? [],
        ]);
    }
}