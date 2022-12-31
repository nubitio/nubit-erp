<?php

use App\Core\EventListener\ExceptionListener;
use App\Core\EventListener\SaleCreatedListener;
use App\Domain\EntryFactory;
use App\Domain\EntryLookupFactory;
use App\Domain\PackageFactory;
use App\Event\EventDispatcherFactory;
use App\Extension\EnvExtension;
use App\Sale\Infrastructure\SaleEntity;
use App\Util\SluggerFactory;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator) {
    $configurator->parameters();

    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('App\\', '../src/')
        ->exclude('../src/{Entity,Util,Extension,Kernel,php,WebClientPrint.php}');

    $services->set(EventDispatcherInterface::class)
        ->factory([EventDispatcherFactory::class, 'create'])
        ->arg('$container', service('service_container'));

    $services->set(SluggerInterface::class)
        ->factory([SluggerFactory::class, 'createSlugger']);

    $services->set('_default', EntrypointLookupInterface::class)
        ->factory([EntryFactory::class, 'create']);

    $services->set('webpack_encore.entrypoint_lookup_collection', EntrypointLookupCollectionInterface::class)
        ->factory([EntryLookupFactory::class, 'create'])
        ->arg('$container', service('service_container'));

    $services->set('package', PackageInterface::class)
        ->factory([PackageFactory::class, 'create']);

    $services->set(Packages::class)
        ->arg('$defaultPackage', service('package'));

    $services->set('webpack_encore.tag_renderer', TagRenderer::class)
        ->arg('$entrypointLookupCollection', service('webpack_encore.entrypoint_lookup_collection'))
        ->arg('$packages', new Reference(Packages::class))
        ->arg('$defaultAttributes', [])
        ->arg('$defaultScriptAttributes', ['defer' => true]);

    $services->set('security.logout_url_generator', LogoutUrlGenerator::class);

    $services->set(EnvExtension::class)
        ->tag('twig.extension');

    //$services->set(ExceptionListener::class)
      //  ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

    $services->set(SaleCreatedListener::class)
        ->tag('doctrine.orm.entity_listener', ['event' => 'postPersist', 'entity' => SaleEntity::class, 'lazy' => true]);
};
