<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

/**
 * Describes what applying a vertical preset changed for a tenant.
 */
final readonly class VerticalPresetApplicationResult
{
    /**
     * @param list<string> $notes
     */
    public function __construct(
        public string $presetKey,
        public int $presetVersion,
        public bool $settingsApplied,
        public bool $defaultWarehouseCreated,
        public int $capabilitiesApplied = 0,
        public array $notes = [],
    ) {}
}
