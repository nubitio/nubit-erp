<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Service\TreasuryActivation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The switch for Treasury. It is the one Treasury endpoint that works while the capability is off. */
final class TreasuryActivationController extends TreasuryApiController
{
    public function __construct(
        TenantCapabilityResolver $capabilities,
        private readonly TreasuryActivation $activation,
    ) {
        parent::__construct($capabilities);
    }

    #[Route('/api/treasury/capability', name: 'treasury_capability', methods: ['PUT'])]
    #[IsGranted('perm:treasury.manage')]
    public function set(Request $request): JsonResponse
    {
        $enabled = $this->body($request)['enabled'] ?? null;
        if (!is_bool($enabled)) {
            throw new UnprocessableEntityHttpException('Indica "enabled": true o false.');
        }

        return $this->json($this->activation->set($enabled));
    }
}
