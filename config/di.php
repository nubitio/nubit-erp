<?php

use App\Domain\EntryFactory;
use App\Domain\EntryLookupFactory;
use App\Domain\PackageFactory;
use App\Event\EventDispatcherFactory;
use App\Infrastructure\DataBaseFactory;
use App\Util\SluggerFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
};
