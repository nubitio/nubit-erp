<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\Service\MovementCategoryResolver;
use App\CashRegister\State\CashMovementWriteProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CashMovementWriteProcessorTest extends TestCase
{
    private function makeMovement(CashMovementType $type = CashMovementType::INCOME, bool $manual = true): CashMovement
    {
        $session = new CashSession();
        $ref = new \ReflectionProperty(CashSession::class, 'id');
        $ref->setValue($session, 7);

        $category = new CashMovementCategory();
        $catRef = new \ReflectionProperty(CashMovementCategory::class, 'id');
        $catRef->setValue($category, 2);
        $category->setType(CashMovementType::INCOME); // required: no default on typed property
        $category->setManual($manual);

        $movement = new CashMovement();
        $movement->setCashSession($session);
        $movement->setAmount('50.00');
        $movement->setDescription('Test movement');
        $movement->setCategory($category); // setCategory syncs $type from category
        $movement->setType($type); // override after setCategory
        $movement->setCreatedAt(new \DateTimeImmutable()); // required: no default

        return $movement;
    }

    // ── Append-only guard ─────────────────────────────────────────────────────

    public function testDeleteIsRejected(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('executeStatement');

        $security = $this->createStub(Security::class);
        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));

        $this->expectException(MethodNotAllowedHttpException::class);
        $processor->process($this->makeMovement(), new Delete(), ['id' => '42']);
    }

    public function testPatchIsRejected(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('executeStatement');

        $security = $this->createStub(Security::class);
        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));

        $this->expectException(MethodNotAllowedHttpException::class);
        $processor->process($this->makeMovement(), new Patch(), ['id' => '5']);
    }

    // ── Unauthenticated ───────────────────────────────────────────────────────

    public function testPostThrowsWhenUserIsNull(): void
    {
        $conn = $this->createStub(Connection::class);
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process($this->makeMovement(), new Post());
    }

    // ── Missing cashSession ───────────────────────────────────────────────────

    public function testPostThrowsWhenCashSessionIsNull(): void
    {
        $conn = $this->createStub(Connection::class);
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));

        $movement = new CashMovement();
        $movement->setType(CashMovementType::INCOME);
        $movement->setDescription('test');
        $movement->setCreatedAt(new \DateTimeImmutable());
        // no cashSession set

        $this->expectException(UnprocessableEntityHttpException::class);
        $processor->process($movement, new Post());
    }

    // ── POST (INSERT) ─────────────────────────────────────────────────────────

    public function testPostInsertsRowAndSetsIdOnEntity(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')->willReturnOnConsecutiveCalls(['status' => 'OPEN', 'opened_by' => 1], [
            'type' => 'income',
            'active' => 1,
        ]);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('INSERT INTO cash_movements'));
        $conn->method('lastInsertId')->willReturn('99');

        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));
        $movement = $this->makeMovement(CashMovementType::INCOME);

        $result = $processor->process($movement, new Post());

        self::assertSame($movement, $result);
        self::assertSame(99, $result->getId());
    }

    public function testARefusedCategoryIsOneTheSystemRecordsItself(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('beginTransaction');
        $conn->expects(self::never())->method('executeStatement');
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('lo registra el sistema');
        $processor->process($this->makeMovement(manual: false), new Post());
    }

    private function movementOfKind(?string $kind): CashMovement
    {
        $session = new CashSession();
        new \ReflectionProperty(CashSession::class, 'id')->setValue($session, 7);

        return new CashMovement()
            ->setCashSession($session)
            ->setAmount('50.00')
            ->setDescription('Pago de movilidad')
            ->setKind($kind);
    }

    public function testAMovementOfAKindIsFiledUnderTheSystemsOwnReason(): void
    {
        foreach ([
            'income' => 'other_income',
            'expense' => 'petty_cash_expense',
            'withdrawal' => 'manager_withdrawal',
        ] as $kind => $code) {
            $conn = $this->createMock(Connection::class);
            $conn->expects(self::once())->method('fetchOne')->with(self::anything(), [$code])->willReturn('11');
            $conn->method('fetchAssociative')->willReturnOnConsecutiveCalls(['status' => 'OPEN', 'opened_by' => 1], [
                'type' => $kind === 'income' ? 'income' : 'expense',
                'active' => 1,
            ]);
            $conn
                ->expects(self::once())
                ->method('executeStatement')
                ->with(
                    self::stringContains('INSERT INTO cash_movements'),
                    self::callback(
                        static fn(array $p): bool => (
                            $p[1] === ($kind === 'income' ? 'CASH_IN' : 'CASH_OUT')
                            && $p[2] === 11
                        ),
                    ),
                );
            $conn->method('lastInsertId')->willReturn('5');
            $security = $this->createStub(Security::class);
            $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

            $result = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn))->process(
                $this->movementOfKind($kind),
                new Post(),
            );

            self::assertNotNull($result);
            self::assertSame(5, $result->getId());
        }
    }

    public function testWithoutCategoryNorKnownKindItIsRefused(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('executeStatement');
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Indica el tipo de movimiento');
        new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn))->process(
            $this->movementOfKind('refund'),
            new Post(),
        );
    }

    public function testPostMapsExpenseTypeToCAShOut(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')->willReturnOnConsecutiveCalls(['status' => 'OPEN', 'opened_by' => 1], [
            'type' => 'expense',
            'active' => 1,
        ]);
        $conn->method('executeStatement')->willReturnCallback(static function (string $sql, array $params) use (
            &$capturedSql,
            &$capturedParams,
        ): int {
            $capturedSql = $sql;
            $capturedParams = $params;

            return 1;
        });
        $conn->method('lastInsertId')->willReturn('1');

        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security, new MovementCategoryResolver($conn));
        $processor->process($this->makeMovement(CashMovementType::EXPENSE), new Post());

        self::assertStringContainsString('INSERT', $capturedSql);
        self::assertSame('CASH_OUT', $capturedParams[1]);
    }
}
