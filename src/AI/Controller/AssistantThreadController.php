<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Service\AssistantThreadService;
use App\Identity\Entity\User;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[RequiresFeature('ai_assistant')]
final class AssistantThreadController extends AbstractController
{
    public function __construct(
        private readonly AssistantThreadService $threadService,
    ) {}

    #[Route('/api/assistant/thread', name: 'api_assistant_thread_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Se requiere un usuario autenticado.'], Response::HTTP_FORBIDDEN);
        }

        $thread = $this->threadService->currentOpenThread($user);

        return $this->json([
            'id' => $thread->getId(),
            'messages' => $this->threadService->serializeRecentMessages($thread),
        ]);
    }

    #[Route('/api/assistant/thread', name: 'api_assistant_thread_new', methods: ['POST'])]
    public function startNew(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Se requiere un usuario autenticado.'], Response::HTTP_FORBIDDEN);
        }

        $thread = $this->threadService->startNewThread($user);

        return $this->json([
            'id' => $thread->getId(),
            'messages' => [],
        ]);
    }
}
