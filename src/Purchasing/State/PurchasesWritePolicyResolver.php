<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;

final readonly class PurchasesWritePolicyResolver
{
    public function __construct(
        private SettingsService $settings,
    ) {
    }

    public function resolve(): PurchasesWritePolicy
    {
        $mode = PurchasesWriteMode::normalize((string) $this->settings->get(
            Constants::PURCHASES_V2_MODE,
            PurchasesWriteMode::DUAL_WRITE
        ));

        if ($mode === PurchasesWriteMode::LEGACY) {
            return new PurchasesWritePolicy(
                mode: $mode,
                writeNewSchema: false,
                skipLegacyPersist: false,
                strict: false,
            );
        }

        if ($mode === PurchasesWriteMode::AUTHORITATIVE) {
            return new PurchasesWritePolicy(
                mode: $mode,
                writeNewSchema: true,
                skipLegacyPersist: true,
                strict: true,
            );
        }

        // dual_write: always writes legacy; V2 write controlled by individual flags
        return new PurchasesWritePolicy(
            mode: $mode,
            writeNewSchema: filter_var(
                $this->settings->get(Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true'),
                FILTER_VALIDATE_BOOLEAN
            ),
            skipLegacyPersist: false,
            strict: filter_var(
                $this->settings->get(Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false'),
                FILTER_VALIDATE_BOOLEAN
            ),
        );
    }
}
