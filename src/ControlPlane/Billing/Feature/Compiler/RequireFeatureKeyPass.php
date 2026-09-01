<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature\Compiler;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails the container build if any feature-gate names a key that is not in
 * {@see FeatureCatalog} — `#[RequiresFeature]` attributes, `->hasFeature('…')` /
 * `->requireFeature('…')` calls, `isFeatureEnabled($…, '…')` calls, and
 * `IS_GRANTED('FEATURE_…')` expressions across `src/`.
 *
 * The "nothing forces the right choice" fix from ADR-013: a typo'd or
 * undeclared feature key breaks `cache:clear` / CI instead of silently failing
 * closed at runtime. Scanning lives in {@see FeatureKeyScanner}.
 */
final class RequireFeatureKeyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $violations = FeatureKeyScanner::unknownKeys($container);
        if ($violations === []) {
            return;
        }

        sort($violations);

        throw new \LogicException(sprintf(
            "Unknown feature key(s) referenced outside FeatureCatalog:\n  - %s\nAdd the key to %s or fix the reference.",
            implode("\n  - ", $violations),
            FeatureCatalog::class,
        ));
    }
}
