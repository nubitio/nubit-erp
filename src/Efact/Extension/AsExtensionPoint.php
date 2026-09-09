<?php

declare(strict_types=1);

namespace App\Efact\Extension;

use Attribute;

/**
 * NB-16 (#342), gap G8: marks an interface as a core seam that a vertical may
 * specialise. The whole point is that adding a vertical rule means registering
 * an implementation — never editing the core.
 *
 * Put it on the interface in its owning context:
 *
 *   #[AsExtensionPoint(
 *       id: 'inventory.stock_validation_policy',
 *       summary: 'Whether a sale line may draw stock it does not have.',
 *       default: TenantStockValidationPolicy::class,
 *       resolution: self::RESOLUTION_BINDING,
 *   )]
 *   interface StockValidationPolicyInterface { ... }
 *
 * {@see ExtensionPointCatalog} discovers every marked interface;
 * ExtensionPointCatalogTest checks the default really implements it and is wired.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsExtensionPoint
{
    /** The default is selected by an explicit alias in config/services.yaml. */
    public const string RESOLUTION_BINDING = 'binding';

    /** The default is the sole implementation and resolves by autowiring. */
    public const string RESOLUTION_AUTOWIRE = 'autowire';

    /**
     * A resolver / #[AsDecorator] chooses the active implementation at runtime
     * from the tenant's capabilities or vertical.
     */
    public const string RESOLUTION_RESOLVER = 'resolver';

    /**
     * @param string $id         stable dotted id, `<context>.<seam>`
     * @param string $summary     one line: what specialising this changes
     * @param class-string $default the implementation used when no vertical overrides it
     * @param string $resolution  one of the RESOLUTION_* constants
     */
    public function __construct(
        public string $id,
        public string $summary,
        public string $default,
        public string $resolution = self::RESOLUTION_AUTOWIRE,
    ) {}
}
