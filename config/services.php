<?php

use App\Domain\EntryLookupFactory;
use App\Infrastructure\DataBaseFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollectionInterface;
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
        ->exclude('../src/{Entity,Util,WebClientPrint.php}');

    $services->set(EntityManagerInterface::class)
        ->factory([DataBaseFactory::class, 'createManager']);

    $services->set(Connection::class)
        ->factory([DriverManager::class, 'getConnection'])
        ->args([
            ['url' => $_ENV['DATABASE_URL']],
        ]);

    $services->set('webpack_encore.entrypoint_lookup_collection', EntrypointLookupCollectionInterface::class)
        ->factory([EntryLookupFactory::class, 'create'])
        ->arg('$container', service('service_container'));

    $services->set(Packages::class);

    $services->set('webpack_encore.tag_renderer', TagRenderer::class)
        ->arg('$entrypointLookupCollection', service('webpack_encore.entrypoint_lookup_collection'))
        ->arg('$packages', new Reference(Packages::class));
};
