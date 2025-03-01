<?php

namespace App;

use App\Core\Infrastructure\Compiler\RemoveUnwantedServices;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RemoveUnwantedServices());
    }

    #[\Override]
    public function boot(): void
    {
        date_default_timezone_set('America/Lima');
        parent::boot();
    }
}
