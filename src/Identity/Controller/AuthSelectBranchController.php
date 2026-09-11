<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Auth\SelectedBranchContext;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Auth\ResponseModeResolver;
use Nubit\AdminBundle\Auth\TokenGenerator;
use Nubit\Platform\Tenant\Context\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issues a fresh token pair bound to the selected branch. Uses the bundle's
 * TokenGenerator: the branch lands in the JWT via EfactTokenClaimsProvider,
 * and the cookie response (incl. the Mercure cookie decorator) comes from the
 * bundle authenticator.
 */
class AuthSelectBranchController extends AbstractController
{
    public function __construct(
        private readonly SecurityComponent $security,
        private readonly BranchRepository $branchRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly TenantContext $tenantContext,
        private readonly TokenGenerator $tokenGenerator,
        private readonly SelectedBranchContext $selectedBranch,
        private readonly ResponseModeResolver $responseModeResolver,
        private readonly JWTAuthenticator $authenticator,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Usuario no autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $branchId = $data['branchId'] ?? null;

        if (!is_string($branchId) || $branchId === '') {
            return new JsonResponse(['message' => 'Debe seleccionar una sucursal'], Response::HTTP_BAD_REQUEST);
        }

        $branch = $this->branchRepository->find($branchId);
        if (!$branch instanceof Branch) {
            return new JsonResponse(['message' => 'Sucursal no encontrada'], Response::HTTP_NOT_FOUND);
        }

        if (!$branch->getIsActive()) {
            return new JsonResponse([
                'message' => 'La sucursal está deshabilitada. Solo está disponible para consultas históricas.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->canSelectBranch($user, $branch)) {
            $this->logger->warning('Unauthorized branch token issuance attempt', [
                'user_id' => $user->getId(),
                'branch_id' => $branchId,
            ]);

            return new JsonResponse(['message' => 'No está autorizado para esta sucursal'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->selectedBranch->set($branch);
            $tokenPair = $this->tokenGenerator->generateTokenPair($user);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate token pair for branch selection', [
                'user_id' => $user->getId(),
                'branch_id' => $branchId,
                'exception' => $e->getMessage(),
            ]);

            return new JsonResponse(['message' => 'Error al generar tokens'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            $this->selectedBranch->set(null);
        }

        if ($this->responseModeResolver->wantsJsonTokens($request)) {
            $payload = $tokenPair->toArray(includeTokens: true);
            $payload['branch'] = ['id' => $branch->getId(), 'name' => $branch->getName()];

            return new JsonResponse($payload);
        }

        $response = $this->authenticator->buildCookieResponse($request, $user, $tokenPair);

        $payload = $tokenPair->toArray(includeTokens: false);
        $payload['branch'] = ['id' => $branch->getId(), 'name' => $branch->getName()];
        $response->setData($payload);

        return $response;
    }

    private function canSelectBranch(User $user, Branch $branch): bool
    {
        if (null !== $user->getDeletedAt() || null === $this->tenantContext->getTenantId()) {
            return false;
        }

        $company = $this->companyRepository->findOneBy([]);
        $branchCompany = $branch->getCompany();
        if (null === $company || null === $branchCompany) {
            return false;
        }

        $companyId = $company->getId();
        $branchCompanyId = $branchCompany->getId();
        $sameCompany =
            null !== $companyId && null !== $branchCompanyId
                ? $companyId === $branchCompanyId
                : $company === $branchCompany;

        if (!$sameCompany) {
            return false;
        }

        // Tenant databases isolate users and branches, while this explicit
        // membership is the user-to-branch authorization source. Roles do not
        // grant implicit access to every branch.
        return $user->hasBranchAccess($branch);
    }
}
