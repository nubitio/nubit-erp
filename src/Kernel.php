<?php

declare(strict_types=1);

namespace App;

use App\ControlPlane\Billing\Feature\Compiler\RequireFeatureKeyPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        // Fails the build if a feature-gate names a key outside FeatureCatalog.
        $container->addCompilerPass(new RequireFeatureKeyPass());
    }
}
