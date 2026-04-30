<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Infrastructure;

use Override;
use RuntimeException;
use App\Billing\Sunat\Domain\BillUtilsInterface;
use App\Organization\Repository\CompanyRepository;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;

readonly class BillUtils implements BillUtilsInterface
{
    public function __construct(
        private CompanyRepository $companyRepository
    ) {
    }

    #[Override]
    public function getCompany(): Company
    {
        $companyEntity = $this->companyRepository->findOneBy([]);
        if (null == $companyEntity) {
            throw new RuntimeException('Company not found');
        }

        $address = new Address();
        $address->setUbigueo($companyEntity->getUbigeoCode() ?? '')
            ->setCodigoPais('PE')
            ->setDepartamento($companyEntity->getDepartment() ?? '')
            ->setProvincia($companyEntity->getProvince() ?? '')
            ->setDistrito($companyEntity->getDistrict() ?? '')
            ->setDireccion($companyEntity->getFiscalAddress());

        $company = new Company();
        $company->setRuc($companyEntity->getRuc())
            ->setRazonSocial($companyEntity->getBusinessName())
            ->setNombreComercial($companyEntity->getTradeName())
            ->setTelephone($companyEntity->getPhone())
            ->setAddress($address);

        return $company;
    }
}
