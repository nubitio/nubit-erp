<?php

namespace App\Event;

use App\EventListener\ProductSubscriber;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class EventDispatcherFactory
{
    public static function create(ContainerInterface $container): EventDispatcher
    {
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber($container->get(ProductSubscriber::class));

        return $eventDispatcher;
    }
}