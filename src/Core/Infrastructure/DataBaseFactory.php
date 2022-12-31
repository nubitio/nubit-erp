<?php

namespace App\Core\Infrastructure;

use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class DataBaseFactory
{
    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    public static function createManager(): EntityManager
    {
        $isDevMode = true;
        $proxyDir = null;
        $cache = DoctrineProvider::wrap(new ArrayAdapter());

        $config = Setup::createAttributeMetadataConfiguration([__DIR__], $isDevMode, $proxyDir, $cache);

        $conn = [
            'url' => $_ENV['DATABASE_URL'],
        ];

        return EntityManager::create($conn, $config);
    }
}
