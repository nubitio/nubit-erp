<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Feature\Compiler\RequireFeatureKeyPass;
use App\Tests\ControlPlane\Billing\Feature\Fixtures\BadlyGatedService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class RequireFeatureKeyPassTest extends TestCase
{
    public function test_passes_on_the_real_source_tree(): void
    {
        // Empty container → only the src/ scan runs. If src/ is clean this must
        // not throw; a real drift would fail here (and in cache:clear).
        new RequireFeatureKeyPass()->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    public function test_rejects_a_service_with_an_unknown_RequiresFeature_key(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(BadlyGatedService::class, new Definition(BadlyGatedService::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/totally_not_a_real_feature/');

        new RequireFeatureKeyPass()->process($container);
    }
}
