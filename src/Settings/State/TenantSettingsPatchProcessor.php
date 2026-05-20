<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Settings\Entity\TenantSettings;
use App\Settings\Service\TenantSettingsService;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProcessorInterface<TenantSettings, TenantSettings> */
final readonly class TenantSettingsPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private TenantSettingsService $service,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TenantSettings
    {
        /** @var TenantSettings $data */
        $settings = $this->service->get();

        foreach ($this->patchedFields() as $field) {
            match ($field) {
                'igv' => $settings->setIgv($data->getIgv()),
                'rc' => $settings->setRc($data->getRc()),
                'printLogo' => $settings->setPrintLogo($data->isPrintLogo()),
                'requireCashRegister' => $settings->setRequireCashRegister($data->isRequireCashRegister()),
                'printMethod' => $settings->setPrintMethod($data->getPrintMethod()),
                'autoInvoice' => $settings->setAutoInvoice($data->isAutoInvoice()),
                'defaultCurrency' => $settings->setDefaultCurrency($data->getDefaultCurrency()),
                'negativeStockPolicy' => $settings->setNegativeStockPolicy($data->getNegativeStockPolicy()),
                default => null,
            };
        }

        $this->service->save($settings);

        return $settings;
    }

    /**
     * @return string[]
     */
    private function patchedFields(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $payload = json_decode($request?->getContent() ?: '{}', true);

        return is_array($payload) ? array_keys($payload) : [];
    }
}
