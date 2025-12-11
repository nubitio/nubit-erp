<?php

namespace App\Core\Service;

use RuntimeException;
use App\Entity\Company;
use App\Repository\CompanyRepository;

class CompanyContext
{
    private ?Company $cached = null;

    public function __construct(
        private readonly CompanyRepository $companyRepository,
    ) {
    }

    public function getCompany(): Company
    {
        if ($this->cached instanceof Company) {
            return $this->cached;
        }

        $company = $this->companyRepository->findOneBy([]);
        if (!$company instanceof Company) {
            throw new RuntimeException('Company is not configured');
        }

        $this->cached = $company;

        return $company;
    }

    public function getId(): string
    {
        // Prefer RUC when available, fallback to internal ID.
        $company = $this->getCompany();

        return (string) ($company->getRuc() ?? $company->getId());
    }

    public function getName(): string
    {
        $company = $this->getCompany();

        return $company->getBusinessName() ?? $company->getTradeName() ?? 'Empresa';
    }
}
