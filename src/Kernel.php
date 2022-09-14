<?php

namespace App;

use App\Domain\Annotation\IgnoreAutowire;
use App\Domain\Compiler\RemoveUnwantedServices;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;

#[IgnoreAutowire]
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        $bundles = [
            new FrameworkBundle(),
            new TwigBundle(),
            new WebpackEncoreBundle(),
            new MonologBundle(),
        ];

        if ('dev' === $this->getEnvironment()) {
            $bundles[] = new WebProfilerBundle();
        }

        return $bundles;
    }

    protected function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RemoveUnwantedServices());
        //$container->registerExtension(new AppExtension());
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'   => '%env(APP_SECRET)%',
            'profiler' => [
                'only_exceptions' => false,
            ],
        ]);

        $container->extension('webpack_encore', [
            'output_path' => '%kernel.project_dir%/public/build'
        ]);

        $container->import(dirname(__DIR__) . '/config/services.php');

        if (isset($this->bundles['WebProfilerBundle'])) {
            $container->extension('web_profiler', [
                'toolbar'             => true,
                'intercept_redirects' => false,
            ]);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        if (isset($this->bundles['WebProfilerBundle'])) {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml')->prefix('/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml')->prefix('/_profiler');
        }

        $routes->import(dirname(__DIR__) . '/src/', 'attribute');
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/../var/cache/' . $this->getEnvironment();
    }

    public function getLogDir(): string
    {
        return __DIR__ . '/../var/log';
    }
}