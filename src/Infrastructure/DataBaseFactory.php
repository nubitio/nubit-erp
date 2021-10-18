<?php

namespace App\Infrastructure;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Setup;

class DataBaseFactory
{
    /**
     * @throws ORMException
     */
    public static function createManager(): EntityManager
    {
        $isDevMode = true;
        $proxyDir = null;
        $cache = null;
        $useSimpleAnnotationReader = false;

        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__), $isDevMode, $proxyDir, $cache, $useSimpleAnnotationReader);

        $conn = array(
            'url' => $_ENV['DATABASE_URL']
        );

        return EntityManager::create($conn, $config);
    }
}