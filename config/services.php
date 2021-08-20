<?php

use App\Infrastructure\DataBaseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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
};
