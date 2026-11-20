<?php

declare(strict_types=1);

namespace App\Tests\BankReconciliation\Service;

use App\AccountsPayable\Entity\SupplierPayment;
use App\BankReconciliation\Entity\BankReconciliationMatch;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Service\BankReconciliationMatchService;
use App\CashRegister\Entity\CashMovement;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class BankReconciliationMatchServiceTest extends TestCase
{
    public function testConfirmMatchPersistsMatchAndMarksLineMatched(): void
    {
        $line = new BankStatementLine();
        $source = new SupplierPayment();
        $user = new User();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['supplierPayment' => $source])->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(BankReconciliationMatch::class)->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(BankReconciliationMatch::class));
        $entityManager->expects(self::once())->method('flush');

        $service = new BankReconciliationMatchService($entityManager);
        $match = $service->confirmMatch($line, $source, $user, BankReconciliationMatch::MANUAL);

        self::assertSame(BankStatementLine::MATCHED, $line->getStatus());
        self::assertSame($source, $match->getSupplierPayment());
        self::assertSame(BankReconciliationMatch::MANUAL, $match->getMatchType());
    }

    public function testConfirmMatchThrowsWhenLineAlreadyMatched(): void
    {
        $line = new BankStatementLine()->setStatus(BankStatementLine::MATCHED);
        $source = new SupplierPayment();
        $user = new User();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $service = new BankReconciliationMatchService($entityManager);

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->confirmMatch($line, $source, $user, BankReconciliationMatch::MANUAL);
    }

    public function testConfirmCashMovementStoresItsV2ScalarIdentifier(): void
    {
        $line = new BankStatementLine();
        $source = new CashMovement();
        new ReflectionProperty(CashMovement::class, 'id')->setValue($source, 321);
        $user = new User();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['cashMovementId' => 321])->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $match = new BankReconciliationMatchService($entityManager)->confirmMatch(
            $line,
            $source,
            $user,
            BankReconciliationMatch::MANUAL,
        );

        self::assertSame(321, $match->getCashMovementId());
        self::assertSame(BankStatementLine::MATCHED, $line->getStatus());
    }

    public function testConfirmMatchThrowsWhenSourceAlreadyMatched(): void
    {
        $line = new BankStatementLine();
        $source = new SupplierPayment();
        $user = new User();

        $existingMatch = new BankReconciliationMatch();
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existingMatch);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');

        $service = new BankReconciliationMatchService($entityManager);

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->confirmMatch($line, $source, $user, BankReconciliationMatch::MANUAL);
    }

    public function testUndoMatchRemovesMatchAndResetsLineStatus(): void
    {
        $line = new BankStatementLine()->setStatus(BankStatementLine::MATCHED);
        $existingMatch = new BankReconciliationMatch();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['bankStatementLine' => $line])->willReturn($existingMatch);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::once())->method('remove')->with($existingMatch);
        $entityManager->expects(self::once())->method('flush');

        new BankReconciliationMatchService($entityManager)->undoMatch($line);

        self::assertSame(BankStatementLine::UNMATCHED, $line->getStatus());
    }

    public function testUndoMatchThrowsWhenNoActiveMatch(): void
    {
        $line = new BankStatementLine()->setStatus(BankStatementLine::MATCHED);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::never())->method('remove');

        $this->expectException(UnprocessableEntityHttpException::class);
        new BankReconciliationMatchService($entityManager)->undoMatch($line);
    }

    public function testIgnoreSetsStatusIgnored(): void
    {
        $line = new BankStatementLine();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        new BankReconciliationMatchService($entityManager)->ignore($line);

        self::assertSame(BankStatementLine::IGNORED, $line->getStatus());
    }

    public function testIgnoreThrowsWhenLineNotUnmatched(): void
    {
        $line = new BankStatementLine()->setStatus(BankStatementLine::IGNORED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(UnprocessableEntityHttpException::class);
        new BankReconciliationMatchService($entityManager)->ignore($line);
    }
}
