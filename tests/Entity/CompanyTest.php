<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Organization\Entity\Company;
use App\Media\Entity\Media;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    public function testCompanyAccessors(): void
    {
        $company = new Company();
        $logo = (new Media())->setPath('/logo.png');

        $company->setRuc('20100000001')
            ->setBusinessName('ACME SAC')
            ->setTradeName('ACME')
            ->setFiscalAddress('Av. Peru 123')
            ->setPhone('999999999')
            ->setLogo($logo)
            ->setUbigeoCode('150101')
            ->setDepartment('Lima')
            ->setProvince('Lima')
            ->setDistrict('Lima')
            ->setBankName('Banco Test')
            ->setBankAccount('123-456')
            ->setBankCci('002123456789')
            ->setDetraccionAccount('DET-001');

        self::assertSame('20100000001', $company->getRuc());
        self::assertSame('ACME SAC', $company->getBusinessName());
        self::assertSame('ACME', $company->getTradeName());
        self::assertSame('Av. Peru 123', $company->getFiscalAddress());
        self::assertSame('999999999', $company->getPhone());
        self::assertSame($logo, $company->getLogo());
        self::assertSame('150101', $company->getUbigeoCode());
        self::assertSame('Lima', $company->getDepartment());
        self::assertSame('Lima', $company->getProvince());
        self::assertSame('Lima', $company->getDistrict());
        self::assertSame('Banco Test', $company->getBankName());
        self::assertSame('123-456', $company->getBankAccount());
        self::assertSame('002123456789', $company->getBankCci());
        self::assertSame('DET-001', $company->getDetraccionAccount());
    }
}
