<?php

declare(strict_types=1);

namespace App\Identity\EventSubscriber;

use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Throwable;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class AuthRefreshAuthorizationSubscriber
{
    /** @param UserProviderInterface<User> $userProvider */
    public function __construct(
        private JWTManagerInterface $jwtManager,
        private UserProviderInterface $userProvider,
        private BranchRepository $branchRepository,
        private CompanyRepository $companyRepository,
        private TenantContext $tenantContext,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ('nubit_admin_auth_refresh' !== $request->attributes->get('_route')) {
            return;
        }

        $refreshToken = $this->refreshToken($request);
        if (null === $refreshToken) {
            return;
        }

        try {
            $payload = $this->jwtManager->decode($refreshToken);
            $user = $this->userProvider->loadUserByIdentifier((string) ($payload['username'] ?? ''));
            /** @var string|int|null $branchId */
            $branchId = $payload['branchId'] ?? null;
            if (null === $branchId) {
                return;
            }
            $branch = is_string($branchId) ? $this->branchRepository->find($branchId) : null;
            $branchCompany = $branch?->getCompany();
            $company = $this->companyRepository->findOneBy([]);

            if (
                null === $this->tenantContext->getTenantId()
                || !is_string($branchId)
                || null === $branch
                || !$user->hasBranchAccess($branch)
                || null === $branchCompany
                || $company?->getId() !== $branchCompany->getId()
            ) {
                $event->setResponse(new JsonResponse(['message' => 'Invalid refresh token'], 401));
            }
        } catch (Throwable) {
            $event->setResponse(new JsonResponse(['message' => 'Invalid refresh token'], 401));
        }
    }

    private function refreshToken(Request $request): ?string
    {
        /** @var mixed $body */
        $body = json_decode($request->getContent(), associative: true);
        $token = is_array($body) && is_string($body['refreshToken'] ?? null) ? $body['refreshToken'] : null;
        if (null === $token) {
            $cookieToken = $request->cookies->get(JWTAuthenticator::REFRESH_COOKIE);
            $token = is_string($cookieToken) ? $cookieToken : null;
        }

        return is_string($token) && '' !== $token ? $token : null;
    }
}
