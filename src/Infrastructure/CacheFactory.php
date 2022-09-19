<?php

namespace App\Infrastructure;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheFactory
{
    public static function create(): CacheItemPoolInterface
    {
        return new FilesystemAdapter();
    }
}