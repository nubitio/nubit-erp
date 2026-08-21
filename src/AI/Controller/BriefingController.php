<?php

declare(strict_types=1);

namespace App\AI\Controller;

use App\AI\Briefing\DailyBriefingService;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Returns the AI-generated daily briefing for the current tenant.
 *
 * The briefing is pre-generated at 07:30 America/Lima by the scheduler and
 * cached in Redis for 20 hours. This endpoint serves the cached value instantly;
 * on cache miss it generates on-demand (< 3 s).
 */
#[RequiresFeature('ai_assistant')]
#[Route('/api/assistant/briefing', name: 'api_assistant_briefing', methods: ['GET'])]
class BriefingController extends AbstractController
{
    public function __construct(
        private readonly DailyBriefingService $briefingService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function __invoke(): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName() ?? 'default';

        try {
            $content = $this->briefingService->generate($tenantName);
        } catch (Throwable $throwable) {
            return new JsonResponse(['content' => DailyBriefingService::DEFAULT_MESSAGE], Response::HTTP_OK);
        }

        return new JsonResponse(['content' => $content]);
    }
}
