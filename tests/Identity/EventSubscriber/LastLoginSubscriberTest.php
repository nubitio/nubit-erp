<?php

declare(strict_types=1);

namespace App\Tests\Identity\EventSubscriber;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\EventSubscriber\LastLoginSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LastLoginSubscriberTest extends TestCase
{
    public function testStampsLastLoginOnTheLoginRouteOnly(): void
    {
        $db = $this->database();
        $subscriber = new LastLoginSubscriber($db);
        $user = $this->user(7);

        $subscriber->onLoginSuccess($this->event($user, 'nubit_admin_auth_refresh'));
        self::assertNull($this->lastLogin($db, 7), 'a token refresh is not a sign-in');

        $subscriber->onLoginSuccess($this->event($user, 'nubit_admin_auth_login'));
        self::assertNotNull($this->lastLogin($db, 7));
        self::assertNull($this->lastLogin($db, 8), 'other users are untouched');
    }

    public function testIgnoresNonUserPrincipals(): void
    {
        $db = $this->database();
        $subscriber = new LastLoginSubscriber($db);

        $subscriber->onLoginSuccess($this->event(new InMemoryUser('svc', null), 'nubit_admin_auth_login'));

        self::assertNull($this->lastLogin($db, 7));
    }

    private function database(): Connection
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE usuario (usuario_id INTEGER PRIMARY KEY, last_login_at TEXT)');
        $db->executeStatement('INSERT INTO usuario (usuario_id) VALUES (7), (8)');

        return $db;
    }

    private function user(int $id): User
    {
        $user = new User()
            ->setUsername('user' . $id)
            ->setRole(new Role());
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }

    private function event(object $user, string $route): LoginSuccessEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $passport = new SelfValidatingPassport(new UserBadge('x', static fn() => $user));
        $token = new PostAuthenticationToken($user, 'main', []);

        return new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport,
            $token,
            $request,
            null,
            'main',
        );
    }

    private function lastLogin(Connection $db, int $id): mixed
    {
        return $db->fetchOne('SELECT last_login_at FROM usuario WHERE usuario_id = ?', [$id]) ?: null;
    }
}
