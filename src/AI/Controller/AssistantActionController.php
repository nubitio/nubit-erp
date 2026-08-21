<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Service\ConfirmProposedActionService;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[RequiresFeature('ai_assistant')]
final class AssistantActionController extends AbstractController
{
    public function __construct(
        private readonly ConfirmProposedActionService $confirmService,
    ) {}

    #[Route('/api/assistant/actions/{id}/confirm', name: 'api_assistant_action_confirm', methods: ['POST'])]
    public function confirm(string $id): JsonResponse
    {
        return $this->json($this->confirmService->confirm($id));
    }

    #[Route('/api/assistant/actions/{id}/cancel', name: 'api_assistant_action_cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        return $this->json($this->confirmService->cancel($id));
    }
}
