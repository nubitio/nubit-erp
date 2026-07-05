<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Api;

use App\ControlPlane\Onboarding\Service\OnboardingService;
use App\ControlPlane\Onboarding\Service\SubdomainValidator;
use App\ControlPlane\Subscription\Payment\BillingCycle;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/account')]
final class AccountProvisioningApiController extends AbstractController
{
    use AccountSessionTrait;

    public function __construct(
        private readonly SubdomainValidator $subdomainValidator,
        private readonly OnboardingService $onboardingService,
        private readonly RateLimiterFactoryInterface $onboardingTenantCreationLimiter,
    ) {}

    #[Route('/subdomains/check', name: 'api_account_check_subdomain', methods: ['GET'])]
    public function checkSubdomain(Request $request): JsonResponse
    {
        $subdomain = (string) $request->query->get('subdomain', '');

        return $this->json($this->subdomainValidator->check($subdomain));
    }

    #[Route('/provisions', name: 'api_account_provision', methods: ['POST'])]
    public function provision(Request $request): JsonResponse
    {
        $accountId = $this->requireAccountId($request);
        if ($accountId instanceof JsonResponse) {
            return $accountId;
        }

        $limiter = $this->onboardingTenantCreationLimiter->create('account_' . $accountId);
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'error' => 'Has alcanzado el limite de creacion de tenants por hoy. Intenta manana.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Payload invalido.'], Response::HTTP_BAD_REQUEST);
        }

        $tenantName = trim((string) ($payload['tenantName'] ?? ''));
        $subdomain = trim((string) ($payload['subdomain'] ?? ''));
        $plan = mb_strtolower(trim((string) ($payload['plan'] ?? 'free')));
        $gateway = mb_strtolower(trim((string) ($payload['gateway'] ?? 'paypal')));
        $billingCycleRaw = mb_strtolower(trim((string) ($payload['billingCycle'] ?? 'monthly')));
        $billingCycle = BillingCycle::tryFrom($billingCycleRaw) ?? BillingCycle::Monthly;
        $adminPassword = (string) ($payload['adminPassword'] ?? '');

        if ($subdomain === '' && $tenantName !== '') {
            $subdomain = $this->subdomainValidator->slugifyTenantName($tenantName);
        }

        try {
            if (!OnboardingService::isPaidPlan($plan)) {
                $result = $this->onboardingService->startFreeProvisioning(
                    $accountId,
                    $tenantName,
                    $subdomain,
                    $adminPassword,
                );

                return $this->json([
                    'token' => $result['token'],
                    'provisionId' => $result['provision_id'],
                    'requiresPayment' => false,
                ]);
            }

            $result = $this->onboardingService->startPaidProvisioning(
                accountId: $accountId,
                tenantName: $tenantName,
                subdomain: $subdomain,
                plan: $plan,
                gateway: $gateway,
                billingCycle: $billingCycle,
                adminPassword: $adminPassword,
            );

            $request->getSession()->set('onboarding_provision_token', $result['token']);

            return $this->json([
                'token' => $result['token'],
                'provisionId' => $result['provision_id'],
                'requiresPayment' => true,
                'approvalUrl' => $result['approval_url'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/provisions/{token}/status', name: 'api_account_provision_status', methods: ['GET'])]
    public function provisionStatus(#[SensitiveParameter] string $token, Request $request): JsonResponse
    {
        $accountId = $this->requireAccountId($request);
        if ($accountId instanceof JsonResponse) {
            return $accountId;
        }

        $status = $this->onboardingService->getProvisionStatus($token, $accountId);
        if ($status === null) {
            return $this->json(['error' => 'Provision no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'status' => $status['status'],
            'tenantName' => $status['tenant_name'] ?? '',
            'domain' => $status['domain'] ?? '',
            'error' => $this->toPublicProvisionError((string) ($status['error_message'] ?? '')),
            'tenantUrl' => $this->resolveTenantUrl($status),
        ]);
    }
}
