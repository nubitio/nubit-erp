<?php

declare(strict_types=1);

namespace App\Efact\Service;

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
        $company = $this->getCompany();

        return $company->getRuc();
    }

    public function getName(): string
    {
        $company = $this->getCompany();

        return $company->getBusinessName();
    }
}
