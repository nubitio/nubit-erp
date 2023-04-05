<?php

namespace App\Billing\Infrastructure;

use App\Billing\Domain\BillUtilsInterface;
use App\Billing\Model\Address\Address;
use App\Billing\Model\Company\Company;
use App\Core\Infrastructure\Service\SettingsService;

class BillUtils implements BillUtilsInterface
{
    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    public function getCompany(): Company
    {
        $company = new Company();
        $company->ruc = strval($this->settingsService->get('invoice_ruc'));
        $company->razonSocial = strval($this->settingsService->get('invoice_razon_social'));
        $company->nombreComercial = strval($this->settingsService->get('invoice_nombre_comercial'));
        $company->address = new Address();
        $company->address->ubigueo = strval($this->settingsService->get('invoice_ubigeo'));
        $company->address->codigoPais = 'PE';
        $company->address->departamento = strval($this->settingsService->get('invoice_departamento'));
        $company->address->provincia = strval($this->settingsService->get('invoice_provincia'));
        $company->address->distrito = strval($this->settingsService->get('invoice_distrito'));
        $company->address->direccion = strval($this->settingsService->get('invoice_direccion'));

        return $company;
    }
}
