<?php

declare(strict_types=1);

namespace App\Settings\Vertical;

/**
 * Versioned onboarding template for a target business type.
 *
 * A preset describes the desired starting point for a tenant: default settings,
 * the capabilities it expects, and a default stock-location layout. Applying a
 * preset configures a tenant without the owner having to reason about branches,
 * warehouses or low-level flags.
 */
interface VerticalPresetInterface
{
    /**
     * Stable machine key, e.g. "single_store_retail".
     */
    public function key(): string;

    /**
     * Monotonic version of this preset's contents. Bump when defaults change.
     */
    public function version(): int;

    /**
     * Human-readable label shown during onboarding.
     */
    public function label(): string;

    /**
     * Settings defaults this preset applies.
     */
    public function settings(): VerticalPresetSettings;

    /**
     * Capability keys this preset expects to be enabled for the tenant.
     *
     * Commercial entitlements still gate what is actually available; this list
     * only expresses the preset's intent.
     *
     * @return list<string>
     */
    public function desiredCapabilities(): array;

    /**
     * Whether this preset operates as a single implicit stock location
     * (Tienda Unica). When true, the applier ensures exactly one default
     * warehouse exists and the UI hides branch/warehouse selection.
     */
    public function usesSingleStockLocation(): bool;
}
