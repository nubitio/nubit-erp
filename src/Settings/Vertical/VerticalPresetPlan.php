<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

use function in_array;

/**
 * What applying a vertical preset to the current tenant would change (NB-8).
 * Produced by {@see VerticalPresetApplier::plan()} without persisting anything,
 * so `tenant:apply-preset --dry-run` can show the diff and a re-apply can be
 * confirmed as a no-op.
 */
final readonly class VerticalPresetPlan
{
    public const string CAP_ENABLE = 'enable';
    public const string CAP_CONFIG = 'config';
    public const string CAP_NOOP = 'noop';
    public const string CAP_SKIP_MANUAL = 'skip-manual';
    public const string CAP_UNKNOWN = 'unknown';

    /**
     * @param list<array{field: string, from: string, to: string}> $settingsChanges
     * @param list<array{key: string, change: string}> $capabilityChanges
     * @param list<string> $notes
     */
    public function __construct(
        public string $presetKey,
        public int $presetVersion,
        public array $settingsChanges,
        public array $capabilityChanges,
        public bool $createsDefaultWarehouse,
        public array $notes,
    ) {}

    public function hasChanges(): bool
    {
        if ($this->settingsChanges !== [] || $this->createsDefaultWarehouse) {
            return true;
        }

        foreach ($this->capabilityChanges as $change) {
            if (in_array($change['change'], [self::CAP_ENABLE, self::CAP_CONFIG], strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function enabledCapabilities(): array
    {
        $keys = [];
        foreach ($this->capabilityChanges as $change) {
            if ($change['change'] === self::CAP_ENABLE) {
                $keys[] = $change['key'];
            }
        }

        return $keys;
    }
}
