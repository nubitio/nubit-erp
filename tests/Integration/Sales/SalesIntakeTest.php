<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\Integration\Sales\SalesClientAccess;
use App\Integration\Sales\SalesIntake;
use App\Integration\Security\ApiClientUser;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SalesIntakeTest extends TestCase
{
    private const string HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testFreshRequestInvokesConfirmationAndLinksTheSaleInTheSameTransaction(): void
    {
        $connection = $this->authorizedConnection();
        $connection
            ->expects(static::once())
            ->method('transactional')
            ->willReturnCallback(static fn(\Closure $operation): mixed => $operation($connection));
        $connection
            ->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn([
                'branch_id' => 'branch-a',
                'payload_hash' => self::HASH,
                'sale_id' => null,
            ]);
        $connection
            ->expects(static::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params): int {
                if (str_starts_with($sql, 'INSERT')) {
                    static::assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
                    static::assertSame(['client-a', 'request-1', 'branch-a', self::HASH], $params);
                } else {
                    static::assertStringContainsString('integration_client_id', $sql);
                    static::assertSame(['sale-1', 'client-a', 'request-1', 'sale-1', 'client-a', 'branch-a'], $params);
                }

                return 1;
            });

        $receipt = $this->intake($connection)->submit(
            $this->client(),
            'branch-a',
            'request-1',
            self::HASH,
            static function (string $clientId, string $branchId): string {
                static::assertSame('client-a', $clientId);
                static::assertSame('branch-a', $branchId);

                return 'sale-1';
            },
        );

        static::assertSame(['sale_id' => 'sale-1', 'replayed' => false], $receipt);
    }

    public function testIdenticalReplayDoesNotConfirmAgain(): void
    {
        $connection = $this->reservedConnection(self::HASH);
        static::assertSame(
            ['sale_id' => 'sale-1', 'replayed' => true],
            $this->intake($connection)->submit(
                $this->client(),
                'branch-a',
                'request-1',
                self::HASH,
                self::neverConfirm(...),
            ),
        );
    }

    public function testAChangedPayloadCannotReuseTheKey(): void
    {
        $this->expectException(ConflictHttpException::class);
        $this->intake($this->reservedConnection(str_repeat('b', 64)))->submit(
            $this->client(),
            'branch-a',
            'request-1',
            self::HASH,
            self::neverConfirm(...),
        );
    }

    /** @return iterable<string, array{string, list<string>, string, string|false}> */
    public static function denied(): iterable
    {
        yield 'foreign tenant' => ['other-tenant', ['sales:write'], 'branch-a', 'branch-a'];
        yield 'missing sales scope' => ['tenant-a', ['documents:write'], 'branch-a', 'branch-a'];
        yield 'unassigned or disabled' => ['tenant-a', ['sales:write'], 'branch-a', false];
        yield 'wrong branch' => ['tenant-a', ['sales:write'], 'branch-b', 'branch-a'];
    }

    /** @param list<string> $scopes */
    #[DataProvider('denied')]
    public function testUnauthorizedRequestsCannotReserveAnIdempotencyKey(
        string $tenant,
        array $scopes,
        string $requestedBranch,
        string|false $allowedBranch,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($allowedBranch);
        $connection->expects(static::never())->method('transactional');
        $this->expectException(AccessDeniedException::class);

        $this->intake($connection)->submit(
            new ApiClientUser('client-a', 'Test API', $tenant, $scopes),
            $requestedBranch,
            'request-1',
            self::HASH,
            self::neverConfirm(...),
        );
    }

    private function reservedConnection(string $hash): Connection&MockObject
    {
        $connection = $this->authorizedConnection();
        $connection
            ->expects(static::once())
            ->method('transactional')
            ->willReturnCallback(static fn(\Closure $operation): mixed => $operation($connection));
        $connection->expects(static::once())->method('executeStatement')->willReturn(0);
        $connection
            ->expects(static::once())
            ->method('fetchAssociative')
            ->willReturn([
                'branch_id' => 'branch-a',
                'payload_hash' => $hash,
                'sale_id' => 'sale-1',
            ]);

        return $connection;
    }

    private function authorizedConnection(): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchOne')->willReturn('branch-a');

        return $connection;
    }

    private function client(): ApiClientUser
    {
        return new ApiClientUser('client-a', 'Test API', 'tenant-a', ['sales:write']);
    }

    private function intake(Connection $connection): SalesIntake
    {
        $context = new TenantContext();
        $context->setTenant(1, 'tenant-a', null, 'request');

        return new SalesIntake($connection, new SalesClientAccess($connection, $context));
    }

    private static function neverConfirm(): string
    {
        static::fail('The underlying sale must not be confirmed again.');
    }
}
