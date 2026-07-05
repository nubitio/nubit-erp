<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

abstract class OperatorApiTestCase extends TestCase
{
    protected function setControllerContainer(object $controller): void
    {
        if (!method_exists($controller, 'setContainer')) {
            return;
        }

        $container = new Container();
        $container->set('security.token_storage', new TokenStorage());
        $controller->setContainer($container);
    }

    protected function connectionRegistry(Connection $connection): ConnectionRegistry
    {
        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($connection);

        return $registry;
    }

    protected function passThruCache(): CacheInterface
    {
        $item = $this->createStub(ItemInterface::class);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static fn(string $key, callable $cb): mixed => $cb($item));

        return $cache;
    }
}
