<?php

namespace App\Domain;

use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class PackageFactory
{
    public static function create(): PathPackage
    {
        return new PathPackage('', new StaticVersionStrategy('v1', '%s?version=%s'));
    }
}
