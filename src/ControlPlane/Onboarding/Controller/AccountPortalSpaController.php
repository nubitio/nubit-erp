<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the React account portal SPA for customer onboarding routes.
 */
final class AccountPortalSpaController extends AbstractController
{
    #[Route('/register', name: 'onboarding_register', methods: ['GET'])]
    #[Route('/register/verify', name: 'onboarding_verify', methods: ['GET'])]
    #[Route('/register/new-tenant', name: 'onboarding_wizard', methods: ['GET'])]
    #[Route('/register/status/{token}', name: 'onboarding_status_page', methods: ['GET'])]
    #[Route('/account/login', name: 'onboarding_account_login', methods: ['GET'])]
    #[Route('/account/dashboard', name: 'onboarding_account_dashboard', methods: ['GET'])]
    #[Route('/account/tenant/{tenantId}', name: 'onboarding_tenant_detail', methods: ['GET'], requirements: ['tenantId' => '\d+'])]
    public function spa(): Response
    {
        return $this->render('account-portal/spa.html.twig');
    }
}