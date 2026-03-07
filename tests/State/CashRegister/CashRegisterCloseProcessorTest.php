<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\Put;
use App\Core\Application\Session\Security;
use App\Dto\Auth\AuthUserDto;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\State\CashRegister\CashRegisterCloseProcessor;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CashRegisterCloseProcessorTest extends TestCase
{
    /** @var Security&MockObject */
    private Security $security;

    /** @var Connection&MockObject */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->connection = $this->createMock(Connection::class);

        $authUser = new AuthUserDto(userId: 1, username: 'cajero');
        $this->security->method('connectedUser')->willReturn($authUser);

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');
        $this->connection->method('rollBack');
        $this->connection->method('executeStatement')->willReturn(0);

        // Default: session exists and is OPEN
        $this->connection->method('fetchAssociative')->willReturn([
            'id'             => 1,
            'opening_amount' => '100.0000',
        ]);

        // Default: all movement sums return 0
        $this->connection->method('fetchOne')->willReturn('0');
    }

    /**
     * TC-3: Closure items that would produce rounding errors with float (0.1 + 0.2)
     * must be accumulated with exact BigDecimal arithmetic.
     */
    public function testClosureItemsAccumulatedWithBrickMath(): void
    {
        /** @var string[] $capturedParams */
        $capturedParams = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$capturedParams): int {
                $capturedParams = $params;
                return 1;
            });

        $dto = new CashRegisterCloseDto();
        $dto->closureItems = [
            ['expectedAmount' => '0.1', 'countedAmount' => '0.2'],
            ['expectedAmount' => '0.1', 'countedAmount' => '0.1'],
        ];

        $processor = $this->buildProcessor();
        $response = $processor->process($dto, new Put(), ['id' => 1]);

        // expectedTotal = 0.1 + 0.1 = 0.20 (exact)
        // countedTotal  = 0.2 + 0.1 = 0.30 (exact — would be 0.30000000000000004 with float)
        // difference    = 0.30 - 0.20 = 0.10
        // params: [closed_by, closing_amount, expected, counted, difference, note, id]
        $this->assertSame('0.2000', $capturedParams[2]); // expected
        $this->assertSame('0.3000', $capturedParams[3]); // counted
        $this->assertSame('0.1000', $capturedParams[4]); // difference
        $this->assertTrue($response->success);
    }

    /**
     * TC-4: Direct expectedAmount/countedAmount strings must produce an exact difference.
     */
    public function testSingleAmountPathUsesExactScale(): void
    {
        /** @var string[] $capturedParams */
        $capturedParams = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$capturedParams): int {
                $capturedParams = $params;
                return 1;
            });

        $dto = new CashRegisterCloseDto();
        $dto->expectedAmount = '123.456';
        $dto->countedAmount = '100.004';
        // difference = 100.004 - 123.456 = -23.452 → rounded half-up to -23.4520

        $processor = $this->buildProcessor();
        $processor->process($dto, new Put(), ['id' => 2]);

        $this->assertSame('123.4560', $capturedParams[2]); // expected
        $this->assertSame('100.0040', $capturedParams[3]); // counted
        $this->assertSame('-23.4520', $capturedParams[4]); // difference
    }

    private function buildProcessor(): CashRegisterCloseProcessor
    {
        return new CashRegisterCloseProcessor(
            connection: $this->connection,
            security: $this->security,
        );
    }
}
