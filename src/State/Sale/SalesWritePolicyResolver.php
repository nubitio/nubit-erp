<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;

final readonly class SalesWritePolicyResolver
{
    public function __construct(
        private SettingsService $settings,
    ) {
    }

    public function resolve(): SalesWritePolicy
    {
        $mode = SalesWriteMode::normalize((string)$this->settings->get(
            Constants::SALES_V2_MODE,
            SalesWriteMode::DUAL_WRITE
        ));

        if ($mode === SalesWriteMode::LEGACY) {
            return new SalesWritePolicy(
                mode: $mode,
                writeNewSchema: false,
                strict: false,
            );
        }

        if ($mode === SalesWriteMode::AUTHORITATIVE) {
            return new SalesWritePolicy(
                mode: $mode,
                writeNewSchema: true,
                strict: true,
            );
        }

        return new SalesWritePolicy(
            mode: $mode,
            writeNewSchema: filter_var(
                $this->settings->get(Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true'),
                FILTER_VALIDATE_BOOLEAN
            ),
            strict: filter_var(
                $this->settings->get(Constants::SALES_V2_DUAL_WRITE_STRICT, 'false'),
                FILTER_VALIDATE_BOOLEAN
            ),
        );
    }
}
