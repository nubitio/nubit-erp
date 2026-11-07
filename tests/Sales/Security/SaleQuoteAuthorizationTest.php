<?php

declare(strict_types=1);

namespace App\Tests\Sales\Security;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Symfony\Security\ResourceAccessChecker;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\RolePermissionRepository;
use App\Identity\Security\PermissionChecker;
use App\Identity\Security\PermissionVoter;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleQuoteItem;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * Evaluates every SaleQuote operation through API Platform's ResourceAccessChecker
 * and the real permission voter. Anonymous and under-privileged callers are denied;
 * grants follow PermissionCatalog, including the super-admin bypass.
 */
final class SaleQuoteAuthorizationTest extends TestCase
{
    private const array ALL_DENIED = [
        'view' => false,
        'create' => false,
        'edit' => false,
        'void' => false,
        'send' => false,
        'convert' => false,
    ];

    public function testEveryQuoteRouteDeclaresExactlyItsCatalogPermission(): void
    {
        $actual = [];
        foreach ($this->operations() as $operation) {
            $actual[$this->operationKey($operation)] = $operation->getSecurity();
        }

        self::assertSame([
            'view-collection' => "is_granted('perm:sale_quotes.view')",
            'view' => "is_granted('perm:sale_quotes.view')",
            'create' => "is_granted('perm:sale_quotes.create')",
            'edit' => "is_granted('perm:sale_quotes.edit')",
            'void' => "is_granted('perm:sale_quotes.void')",
            'convert' => "is_granted('perm:sale_quotes.convert') and is_granted('perm:sales.create')",
            'send' => "is_granted('perm:sale_quotes.send')",
        ], $actual);
    }

    public function testQuoteItemsAreNotAnApiResourceAndBranchFilteringStillApplies(): void
    {
        self::assertSame([], (new \ReflectionClass(SaleQuoteItem::class))->getAttributes(ApiResource::class));

        $branch = new \ReflectionProperty(SaleQuote::class, 'branch');
        self::assertNotSame([], $branch->getAttributes(ManyToOne::class));
        $join = $branch->getAttributes(JoinColumn::class)[0]->newInstance();
        self::assertSame('branch_id', $join->name);
        self::assertNotSame([], (new \ReflectionClass(SaleQuote::class))->getAttributes(ApiResource::class));
    }

    /**
     * @param list<string> $grants
     * @param array<string, bool> $expected
     */
    #[DataProvider('callers')]
    public function testResourceAccessCheckerGrantsOnlyTheHeldPermissions(
        ?int $roleId,
        array $grants,
        array $expected,
    ): void {
        $checker = $this->accessChecker($roleId, $grants);

        foreach ($this->operations() as $operation) {
            $key = $this->decisionKey($this->operationKey($operation));
            $security = $operation->getSecurity();
            self::assertIsString($security);

            self::assertSame(
                $expected[$key],
                $checker->isGranted(SaleQuote::class, $security),
                sprintf('%s for role %s', $key, $roleId === null ? 'anonymous' : (string) $roleId),
            );
        }
    }

    /**
     * @return iterable<string, array{?int, list<string>, array<string, bool>}>
     */
    public static function callers(): iterable
    {
        $quote = [
            'sale_quotes.view',
            'sale_quotes.create',
            'sale_quotes.edit',
            'sale_quotes.send',
            'sale_quotes.convert',
            'sale_quotes.void',
        ];
        $cashier = [
            'sales.view', 'sales.create', 'sales.collect', 'pos.access', 'pos.offline_sale',
            'cash.view', 'cash.open', 'cash.close', 'inventory.view',
            'catalog.view', 'customers.view', 'customers.create',
        ];

        yield 'anonymous' => [null, [], self::ALL_DENIED];
        yield 'authenticated without permissions' => [4, [], self::ALL_DENIED];
        yield 'view only' => [5, ['sale_quotes.view'], array_merge(self::ALL_DENIED, ['view' => true])];
        yield 'create only' => [6, ['sale_quotes.create'], array_merge(self::ALL_DENIED, ['create' => true])];
        yield 'edit only' => [7, ['sale_quotes.edit'], array_merge(self::ALL_DENIED, ['edit' => true])];
        yield 'void only' => [8, ['sale_quotes.void'], array_merge(self::ALL_DENIED, ['void' => true])];
        yield 'send only' => [9, ['sale_quotes.send'], array_merge(self::ALL_DENIED, ['send' => true])];
        yield 'convert without sales.create' => [10, ['sale_quotes.convert'], self::ALL_DENIED];
        yield 'sales.create without convert' => [11, ['sales.create'], self::ALL_DENIED];
        yield 'convert and sales.create' => [
            12,
            ['sale_quotes.convert', 'sales.create'],
            array_merge(self::ALL_DENIED, ['convert' => true]),
        ];
        yield 'cashier seed cannot use quotes' => [3, $cashier, self::ALL_DENIED];
        yield 'administrator seed keeps quote and sale creation' => [
            2,
            [...$quote, 'sales.create'],
            [
                'view' => true,
                'create' => true,
                'edit' => true,
                'void' => true,
                'send' => true,
                'convert' => true,
            ],
        ];
        yield 'superadmin bypasses an empty grant list' => [
            1,
            [],
            [
                'view' => true,
                'create' => true,
                'edit' => true,
                'void' => true,
                'send' => true,
                'convert' => true,
            ],
        ];
    }

    /**
     * @param list<string> $grants
     */
    private function accessChecker(?int $roleId, array $grants): ResourceAccessChecker
    {
        $tokenStorage = new TokenStorage();
        if ($roleId !== null) {
            $user = $this->user($roleId);
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $repository = $this->createStub(RolePermissionRepository::class);
        $repository->method('findPermissionKeysByRoleId')->willReturn($grants);

        $permissionChecker = new PermissionChecker(
            new Security(new class($tokenStorage) implements ContainerInterface {
                public function __construct(private TokenStorage $tokenStorage) {}

                public function get(string $id): TokenStorage
                {
                    if ($id !== 'security.token_storage') {
                        throw new \RuntimeException($id);
                    }

                    return $this->tokenStorage;
                }

                public function has(string $id): bool
                {
                    return $id === 'security.token_storage';
                }
            }),
            $repository,
        );

        $authorizationChecker = new AuthorizationChecker(
            $tokenStorage,
            new AccessDecisionManager([new PermissionVoter($permissionChecker)]),
        );

        return new ResourceAccessChecker(
            new ExpressionLanguage(),
            new AuthenticationTrustResolver(),
            null,
            $tokenStorage,
            $authorizationChecker,
        );
    }

    private function user(int $roleId): User
    {
        $role = new Role();
        new \ReflectionProperty(Role::class, 'id')->setValue($role, $roleId);
        $role->setName('role-' . $roleId);

        return new User()->setUsername('user-' . $roleId)->setPassword('unused')->setRole($role);
    }

    /**
     * @return list<Operation>
     */
    private function operations(): array
    {
        $apiResource = (new \ReflectionClass(SaleQuote::class))->getAttributes(ApiResource::class)[0]->newInstance();
        $operations = [];
        foreach ($apiResource->getOperations() ?? [] as $operation) {
            self::assertInstanceOf(Operation::class, $operation);
            $operations[] = $operation;
        }

        return $operations;
    }

    private function operationKey(Operation $operation): string
    {
        return match (true) {
            $operation instanceof GetCollection => 'view-collection',
            $operation instanceof Get => 'view',
            $operation instanceof Delete => 'void',
            $operation instanceof Patch => 'edit',
            $operation->getName() === 'sale_quote_convert' => 'convert',
            $operation->getName() === 'sale_quote_send' => 'send',
            $operation instanceof Post => 'create',
            default => throw new \LogicException($operation::class),
        };
    }

    private function decisionKey(string $operationKey): string
    {
        return $operationKey === 'view-collection' ? 'view' : $operationKey;
    }
}
