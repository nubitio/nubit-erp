<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Resolves vertical presets by key from the tagged collection of presets.
 */
final readonly class VerticalPresetRegistry
{
    /** @var array<string, VerticalPresetInterface> */
    private array $presets;

    /**
     * @param iterable<VerticalPresetInterface> $presets
     */
    public function __construct(
        #[AutowireIterator('app.vertical_preset')]
        iterable $presets,
    ) {
        $indexed = [];
        foreach ($presets as $preset) {
            $indexed[$preset->key()] = $preset;
        }
        $this->presets = $indexed;
    }

    public function has(string $key): bool
    {
        return isset($this->presets[$key]);
    }

    public function get(string $key): VerticalPresetInterface
    {
        if (!isset($this->presets[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown vertical preset "%s". Available: %s.',
                $key,
                implode(', ', array_keys($this->presets)) ?: '(none)',
            ));
        }

        return $this->presets[$key];
    }

    /**
     * @return list<VerticalPresetInterface>
     */
    public function all(): array
    {
        return array_values($this->presets);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->presets);
    }
}
