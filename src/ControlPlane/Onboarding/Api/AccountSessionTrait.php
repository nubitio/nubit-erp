<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait AccountSessionTrait
{
    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Request $request): array
    {
        $data = json_decode($request->getContent(), associative: true);

        return \is_array($data) ? $data : [];
    }

    protected function onboardingDomainSuffix(): string
    {
        $root = trim(
            (string) (
                $_ENV['ONBOARDING_DOMAIN'] ?? $_SERVER['ONBOARDING_DOMAIN'] ?? getenv('ONBOARDING_DOMAIN')
                ?: 'localhost'
            ),
        );

        if ($root === '') {
            $root = 'localhost';
        }

        return '.' . $root;
    }

    protected function getSessionAccountId(Request $request): int
    {
        return (int) $request->getSession()->get('onboarding_account_id', 0);
    }

    protected function requireAccountId(Request $request): int|JsonResponse
    {
        $accountId = $this->getSessionAccountId($request);
        if ($accountId <= 0) {
            return $this->json(['error' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        return $accountId;
    }

    protected function bindAccountSession(Request $request, int $accountId, string $email): void
    {
        $session = $request->getSession();
        $session->set('onboarding_account_id', $accountId);
        $session->set('onboarding_account_email', mb_strtolower(trim($email)));
        $session->remove('onboarding_pending_email');
    }

    protected function clearAccountSession(Request $request): void
    {
        $session = $request->getSession();
        $session->remove('onboarding_account_id');
        $session->remove('onboarding_account_email');
        $session->remove('onboarding_pending_email');
        $session->remove('onboarding_provision_token');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeProvisions(array $rows): array
    {
        return array_map(static function (array $row): array {
            $domain = (string) ($row['domain'] ?? '');
            $row['tenant_url'] = $domain !== '' ? 'https://' . $domain : '';

            return $row;
        }, $rows);
    }

    protected function toPublicProvisionError(string $errorMessage): string
    {
        $errorMessage = trim($errorMessage);
        if ($errorMessage === '') {
            return '';
        }

        return 'No pudimos completar la creacion de tu empresa. Intenta nuevamente en unos minutos.';
    }

    /**
     * @param array<string, mixed> $status
     */
    protected function resolveTenantUrl(array $status): string
    {
        $domain = (string) ($status['primary_domain'] ?? $status['domain'] ?? '');
        if ($domain === '') {
            return '';
        }

        return 'https://' . $domain;
    }
}