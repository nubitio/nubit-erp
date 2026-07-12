<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

abstract class OperatorApiTestCase extends TestCase
{
    private ?TokenStorage $tokenStorage = null;

    protected function setControllerContainer(object $controller): void
    {
        if (!method_exists($controller, 'setContainer')) {
            return;
        }

        $this->tokenStorage = new TokenStorage();

        $container = new Container();
        $container->set('security.token_storage', $this->tokenStorage);
        $controller->setContainer($container);
    }

    /**
     * Puts an authenticated user identifier behind `$controller->getUser()`.
     * Call after setControllerContainer().
     *
     * @param list<string> $roles
     */
    protected function authenticateAs(string $identifier, array $roles = ['ROLE_SUPER_ADMIN']): void
    {
        $this->tokenStorage?->setToken(
            new UsernamePasswordToken(new InMemoryUser($identifier, null, $roles), 'control_plane', $roles),
        );
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
