<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\BillUtilsInterface;
use App\Core\Infrastructure\Service\SettingsService;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;

class BillUtils implements BillUtilsInterface
{
    public function __construct(private readonly SettingsService $settingsService)
    {
    }

    #[\Override]
    public function getCompany(): Company
    {
        $address = new Address();
        $address->setUbigueo(strval($this->settingsService->get('invoice_ubigeo')))
            ->setCodigoPais('PE')
            ->setDepartamento(strval($this->settingsService->get('invoice_departamento')))
            ->setProvincia(strval($this->settingsService->get('invoice_provincia')))
            ->setDistrito(strval($this->settingsService->get('invoice_distrito')))
            ->setDireccion(strval($this->settingsService->get('invoice_direccion')));
        $company = new Company();
        $company->setRuc(strval($this->settingsService->get('invoice_ruc')))
            ->setRazonSocial(strval($this->settingsService->get('invoice_razon_social')))
            ->setNombreComercial(strval($this->settingsService->get('invoice_nombre_comercial')))
            ->setAddress($address);

        return $company;
    }
}
