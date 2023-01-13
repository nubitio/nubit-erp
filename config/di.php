<?php

use App\Core\Infrastructure\EventListener\ExceptionListener;
use App\Core\Infrastructure\EventListener\SaleCreatedListener;
use App\Core\Infrastructure\Extension\EnvExtension;
use App\Sale\Infrastructure\SaleEntity;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator) {
    $configurator->parameters();

    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('App\\', '../src/')
        ->exclude('../src/{Entity,Util,Extension,Kernel,php,WebClientPrint.php}');

    $services->set(EnvExtension::class)
        ->tag('twig.extension');

    $services->set(ExceptionListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

    $services->set(SaleCreatedListener::class)
        ->tag('doctrine.orm.entity_listener', ['event' => 'postPersist', 'entity' => SaleEntity::class, 'lazy' => true]);
};
