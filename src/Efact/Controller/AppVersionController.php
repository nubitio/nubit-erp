<?php

declare(strict_types=1);

namespace App\Efact\Controller;

use App\Efact\Service\AppReleaseInfo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AppVersionController
{
    #[Route('/health/app-version', name: 'app_version', methods: ['GET'])]
    public function __invoke(AppReleaseInfo $release): JsonResponse
    {
        return new JsonResponse($release->get(), headers: ['Cache-Control' => 'no-store, max-age=0']);
    }
}
