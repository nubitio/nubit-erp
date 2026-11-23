<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Get;
use App\CashRegister\State\CashPosStateProvider;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class CashPosStateProviderTest extends TestCase
{
    /**
     * @param array<string, mixed>|false $sessionRow
     * @param list<array<string, mixed>> $freeRegisters
     * @param list<array<string, mixed>> $busyRegisters
     */
    private function provider(
        ?AuthUserDto $user,
        array|false $sessionRow,
        array $freeRegisters = [],
        ?string $branchId = 'branch-1',
        array $busyRegisters = [],
    ): CashPosStateProvider {
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn($user);

        $branch = $this->createStub(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn($branchId);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($sessionRow);
        $connection->method('fetchAllAssociative')->willReturnCallback(static fn(string $sql): array => str_contains(
            $sql,
            'JOIN cash_sessions',
        )
                ? $busyRegisters
                : $freeRegisters);

        return new CashPosStateProvider($connection, $security, $branch);
    }

    public function testRejectsAnonymousUsers(): void
    {
        $this->expectException(UnauthorizedHttpException::class);

        $this->provider(null, false)->provide(new Get());
    }

    public function testReturnsTheUsersOpenSessionAndNoRegistersToOpen(): void
    {
        $state = $this->provider(new AuthUserDto(userId: 7, username: 'cajero'), [
            'id' => '42',
            'cash_register_id' => '3',
            'name' => 'Caja 3',
            'opened_at' => '2026-11-23 08:30:00',
            'opening_amount' => '150.5',
        ])->provide(new Get());

        static::assertNotNull($state->session);
        static::assertSame(42, $state->session['id']);
        static::assertSame(3, $state->session['cashRegisterId']);
        static::assertSame('Caja 3', $state->session['cashRegisterName']);
        static::assertSame('150.50', $state->session['openingAmount']);
        static::assertStringStartsWith('2026-11-23T08:30:00', $state->session['openedAt']);
        static::assertSame([], $state->availableRegisters);
    }

    public function testListsTheRegistersOfTheBranchThatAreFreeWhenTheUserHasNoSession(): void
    {
        $state = $this->provider(new AuthUserDto(userId: 7, username: 'cajero'), false, [
            ['id' => '1', 'name' => 'Caja 1'],
            ['id' => '2', 'name' => 'Caja 2'],
        ])->provide(new Get());

        static::assertNull($state->session);
        static::assertSame(
            [['id' => 1, 'name' => 'Caja 1'], ['id' => 2, 'name' => 'Caja 2']],
            $state->availableRegisters,
        );
    }

    public function testOffersNoRegistersWithoutBranchContext(): void
    {
        $state = $this->provider(
            new AuthUserDto(userId: 7, username: 'cajero'),
            false,
            [['id' => '1', 'name' => 'Caja 1']],
            branchId: null,
        )->provide(new Get());

        static::assertNull($state->session);
        static::assertSame([], $state->availableRegisters);
    }

    public function testTellsWhoHasTheOtherRegistersOpenSoTheCashierKnowsWhomToWaitFor(): void
    {
        $state = $this->provider(
            new AuthUserDto(userId: 8, username: 'luis'),
            false,
            [],
            busyRegisters: [
                ['id' => '1', 'name' => 'Caja Principal', 'username' => 'maria', 'opened_at' => '2026-11-23 11:55:00'],
                ['id' => '2', 'name' => 'Caja 2', 'username' => null, 'opened_at' => '2026-11-23 12:10:00'],
            ],
        )->provide(new Get());

        static::assertSame([], $state->availableRegisters);
        static::assertSame('Caja Principal', $state->busyRegisters[0]['name']);
        static::assertSame('maria', $state->busyRegisters[0]['cashier']);
        static::assertStringStartsWith('2026-11-23T11:55:00', $state->busyRegisters[0]['openedAt']);
        static::assertNull($state->busyRegisters[1]['cashier']);
    }

    public function testListsNoBusyRegistersWhileTheUserHasASessionOfTheirOwn(): void
    {
        $state = $this->provider(
            new AuthUserDto(userId: 7, username: 'cajero'),
            [
                'id' => '42',
                'cash_register_id' => '3',
                'name' => 'Caja 3',
                'opened_at' => '2026-11-23 08:30:00',
                'opening_amount' => '100',
            ],
            [],
            busyRegisters: [['id' => '1', 'name' => 'X', 'username' => 'otro', 'opened_at' => '2026-11-23 09:00:00']],
        )->provide(new Get());

        static::assertSame([], $state->busyRegisters);
    }
}
