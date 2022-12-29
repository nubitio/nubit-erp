<?php

namespace App\Domain;

use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;

class EntryFactory
{
    public static function create(): EntrypointLookup
    {
        return new EntrypointLookup(dirname(__DIR__, 2).'/public/build/entrypoints.json');
    }
}
