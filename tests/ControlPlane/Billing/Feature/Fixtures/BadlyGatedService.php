<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature\Fixtures;

use Nubit\Platform\Feature\Attribute\RequiresFeature;

/**
 * Test fixture only — carries a #[RequiresFeature] with a key that is NOT in
 * FeatureCatalog, so RequireFeatureKeyPass must reject it.
 */
#[RequiresFeature('totally_not_a_real_feature')]
final class BadlyGatedService
{
    public function run(): void {}
}
