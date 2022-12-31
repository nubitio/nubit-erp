<?php

namespace App;

use App\Auth\Domain\AppAuthenticator;
use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Core\Infrastructure\Compiler\RemoveUnwantedServices;
use App\User\Domain\UserProvider;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\WebpackEncoreBundle\WebpackEncoreBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

#[IgnoreAutowire]
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        $bundles = [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new DebugBundle(),
            new TwigBundle(),
            new TwigExtraBundle(),
            new WebpackEncoreBundle(),
            new SecurityBundle(),
            new MonologBundle(),
            new EasyAdminBundle(),
            new MakerBundle(),
        ];

        if ('dev' === $this->getEnvironment()) {
            $bundles[] = new WebProfilerBundle();
        }

        return $bundles;
    }

    protected function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RemoveUnwantedServices());
        // $container->registerExtension(new AppExtension());
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => '%env(APP_SECRET)%',
            'http_method_override' => true,
            'profiler'             => [
                'only_exceptions' => false,
            ],
            'mailer'               => [
                'dsn' => '%env(MAILER_DSN)%',
            ],
        ]);

        $container->extension('security', [
            'providers'        => [
                'user_provider' => [
                    'id' => UserProvider::class,
                ],
            ],
            'firewalls'        => [
                'dev'  => [
                    'pattern'  => '^/(_(profiler|wdt)|css|images|js)/',
                    'security' => false,
                ],
                'main' => [
                    'lazy'                 => true,
                    'provider'             => 'user_provider',
                    'custom_authenticator' => AppAuthenticator::class,
                    'logout'               => [
                        'path'   => 'logout',
                        'target' => 'login_index',
                    ],
                ],
            ],
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
                ],
            ],
            'access_control'   => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'url' => '%env(resolve:DATABASE_URL)%',
            ],
            'orm'  => [
                'auto_generate_proxy_classes' => true,
                'naming_strategy'             => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping'                => true,
                'mappings'                    => [
                    'App' => [
                        'is_bundle' => false,
                        'type'      => 'attribute',
                        'dir'       => '%kernel.project_dir%/src/',
                        'prefix'    => 'App',
                        'alias'     => 'App',
                    ],
                ],
            ],
        ]);

        $container->extension('monolog', [
            'handlers' => [
                'main' => [
                    'type'  => 'stream',
                    'path'  => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
            ],
        ]);

        $container->extension('twig', [
            'default_path'     => '%kernel.project_dir%/templates',
            'debug'            => '%kernel.debug%',
            'strict_variables' => '%kernel.debug%',
            'globals'          => [
                'settings' => '@App\Core\Infrastructure\Service\SettingsService',
            ],
        ]);

        $container->extension('webpack_encore', [
            'output_path' => '%kernel.project_dir%/public/build',
        ]);

        if (isset($this->bundles['WebProfilerBundle'])) {
            $container->extension('web_profiler', [
                'toolbar'             => true,
                'intercept_redirects' => false,
            ]);
        }

        $container->import(dirname(__DIR__) . '/config/di.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        if (isset($this->bundles['WebProfilerBundle'])) {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml')->prefix('/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml')->prefix('/_profiler');
        }

        $routes->import(dirname(__DIR__) . '/src/', 'attribute');
    }
}
