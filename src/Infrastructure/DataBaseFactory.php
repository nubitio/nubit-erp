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
            'url' => 'mysql://c1_restaurante:Master.2017@jgm90.com:3306/c1_restaurante'
        );

        return EntityManager::create($conn, $config);
    }
}