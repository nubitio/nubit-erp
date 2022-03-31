<?php

namespace App\Domain;

use Psr\Container\ContainerInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollection;

class EntryLookupFactory
{
    public static function create(ContainerInterface $container): EntrypointLookupCollection
    {
        return new EntrypointLookupCollection($container, '_default');
    }
}