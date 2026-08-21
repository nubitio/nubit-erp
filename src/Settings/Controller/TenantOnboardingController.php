<?php

declare(strict_types=1);

namespace App\Settings\Controller;

use App\Catalog\Entity\VoucherSeries;
use App\Catalog\Repository\VoucherSeriesRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tenant/onboarding', name: 'api_tenant_onboarding_')]
#[IsGranted('ROLE_USER')]
final class TenantOnboardingController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CompanyRepository $companyRepository,
        private readonly BranchRepository $branchRepository,
        private readonly WarehouseRepository $warehouseRepository,
        private readonly VoucherSeriesRepository $voucherSeriesRepository,
        private readonly VoucherTypeRepository $voucherTypeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/snapshot', name: 'snapshot', methods: ['GET'])]
    public function snapshot(): JsonResponse
    {
        if (!$this->hasTenantContext()) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_CONFLICT);
        }

        return $this->json($this->buildSnapshot());
    }

    #[Route('/company', name: 'company', methods: ['POST'])]
    public function saveCompany(Request $request): JsonResponse
    {
        if (!$this->hasTenantContext()) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_CONFLICT);
        }

        try {
            $payload = $request->toArray();
            $company = $this->defaultCompany();
            $company
                ->setRuc($this->requiredString($payload, 'ruc'))
                ->setBusinessName($this->requiredString($payload, 'businessName'))
                ->setTradeName($this->optionalString($payload, 'tradeName'))
                ->setFiscalAddress($this->requiredString($payload, 'fiscalAddress'))
                ->setPhone($this->optionalString($payload, 'phone'));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $this->json(['company' => $this->serializeCompany($company)]);
    }

    #[Route('/branch', name: 'branch', methods: ['POST'])]
    public function saveBranch(Request $request): JsonResponse
    {
        if (!$this->hasTenantContext()) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_CONFLICT);
        }

        try {
            $payload = $request->toArray();
            $company = $this->requireCompany();
            $branch = $this->defaultBranch();
            $branch
                ->setName($this->requiredString($payload, 'name'))
                ->setAddress($this->optionalString($payload, 'address'))
                ->setCompany($company);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($branch);
        $this->entityManager->flush();

        $invoiceSeries = strtoupper($this->optionalString($payload, 'invoiceSeries') ?? 'F001');
        $receiptSeries = strtoupper($this->optionalString($payload, 'receiptSeries') ?? 'B001');
        $this->upsertVoucherSeries($branch, '01', $invoiceSeries);
        $this->upsertVoucherSeries($branch, '03', $receiptSeries);
        $this->upsertVoucherSeries($branch, '00', 'N001');
        $this->entityManager->flush();

        return $this->json(['branch' => $this->serializeBranch($branch)]);
    }

    #[Route('/warehouse', name: 'warehouse', methods: ['POST'])]
    public function saveWarehouse(Request $request): JsonResponse
    {
        if (!$this->hasTenantContext()) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_CONFLICT);
        }

        try {
            $payload = $request->toArray();
            $branch = $this->requireBranch();
            $warehouse = $this->defaultWarehouse();
            $warehouse->setName($this->requiredString($payload, 'name'))->setBranch($branch);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($warehouse);
        $this->entityManager->flush();

        return $this->json(['warehouse' => $this->serializeWarehouse($warehouse)]);
    }

    private function hasTenantContext(): bool
    {
        return $this->tenantContext->getTenantName() !== null;
    }

    private function defaultCompany(): Company
    {
        return $this->companyRepository->findOneBy([]) ?? new Company();
    }

    private function requireCompany(): Company
    {
        $company = $this->companyRepository->findOneBy([]);
        if (!$company instanceof Company) {
            throw new \InvalidArgumentException('Debe configurar la empresa antes de continuar.');
        }

        return $company;
    }

    private function defaultBranch(): Branch
    {
        return $this->branchRepository->findOneBy([], ['name' => 'ASC']) ?? new Branch();
    }

    private function requireBranch(): Branch
    {
        $branch = $this->branchRepository->findOneBy([], ['name' => 'ASC']);
        if (!$branch instanceof Branch) {
            throw new \InvalidArgumentException('Debe configurar la sucursal antes de continuar.');
        }

        return $branch;
    }

    private function defaultWarehouse(): Warehouse
    {
        return $this->warehouseRepository->findOneBy([], ['name' => 'ASC']) ?? new Warehouse();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Debe indicar "%s".', $key));
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function upsertVoucherSeries(Branch $branch, string $voucherTypeCode, string $series): void
    {
        $voucherType = $this->voucherTypeRepository->find($voucherTypeCode);
        if ($voucherType === null) {
            return;
        }

        $existing = $this->voucherSeriesRepository->findOneBy([
            'branch' => $branch,
            'voucherType' => $voucherType,
        ]);
        $record = $existing ?? new VoucherSeries();
        $record->setBranch($branch)->setVoucherType($voucherType)->setSeries($series);

        $this->entityManager->persist($record);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(): array
    {
        return [
            'company' => ($company = $this->companyRepository->findOneBy([])) === null
                ? null
                : $this->serializeCompany($company),
            'branch' => ($branch = $this->branchRepository->findOneBy([], ['name' => 'ASC'])) === null
                ? null
                : $this->serializeBranch($branch),
            'warehouse' => ($warehouse = $this->warehouseRepository->findOneBy([], ['name' => 'ASC'])) === null
                ? null
                : $this->serializeWarehouse($warehouse),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCompany(Company $company): array
    {
        return [
            'id' => $company->getId(),
            'ruc' => $company->getRuc(),
            'businessName' => $company->getBusinessName(),
            'tradeName' => $company->getTradeName(),
            'fiscalAddress' => $company->getFiscalAddress(),
            'phone' => $company->getPhone(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBranch(Branch $branch): array
    {
        return [
            'id' => $branch->getId(),
            'name' => $branch->getName(),
            'address' => $branch->getAddress(),
            'phone' => $branch->getPhone(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWarehouse(Warehouse $warehouse): array
    {
        return [
            'id' => $warehouse->getId(),
            'name' => $warehouse->getName(),
            'branchId' => $warehouse->getBranch()?->getId(),
        ];
    }
}
