<?php

declare(strict_types=1);

namespace App\Tests\BankReconciliation\Service;

use App\BankReconciliation\Entity\BankAccount;
use App\BankReconciliation\Entity\BankReconciliationMatch;
use App\BankReconciliation\Entity\BankStatementLine;
use App\BankReconciliation\Service\BankReconciliationMatchingService;
use App\BankReconciliation\Service\BankReconciliationMatchService;
use App\BankReconciliation\Service\TreasuryReconciliationSource;
use App\CashRegister\Service\CashMovementHydrator;
use App\Identity\Entity\User;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Settings\Entity\TenantCapability;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Tests\Treasury\TreasuryTestDatabase;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Port\MoneyMovementRequest;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** A bank statement line is reconciled against the movements of its own bank account's Treasury account. */
#[AllowMockObjectsWithoutExpectations]
final class TreasuryReconciliationTest extends TestCase
{
    private const string BCP = 'bank-bcp';
    private const string BBVA = 'bank-bbva';

    public function testCandidatesAreTheUnreconciledMovementsOfThatBankAccountWithTheExactAmountInTheWindow(): void
    {
        $kit = $this->kit();
        $this->move($kit, 'bcp', 'P-1', Direction::Out, '118.00', '2026-11-12');
        $this->move($kit, 'bcp', 'P-2', Direction::Out, '118.00', '2026-11-30'); // outside the window
        $this->move($kit, 'bcp', 'P-3', Direction::Out, '99.00', '2026-11-12'); // another amount
        $this->move($kit, 'bcp', 'P-4', Direction::In, '118.00', '2026-11-12'); // wrong direction
        $this->move($kit, 'bbva', 'P-5', Direction::Out, '118.00', '2026-11-12'); // another bank account
        $this->move($kit, 'bcp', 'P-6', Direction::Out, '118.00', '2026-11-12');
        $kit->db->insert('bank_reconciliation_match', ['treasury_movement_id' => $this->idOf($kit, 'P-6')]);

        $found = $this->source($kit)->candidates(
            self::BCP,
            BigDecimal::of('-118.00'),
            new DateTimeImmutable('2026-11-09'),
            new DateTimeImmutable('2026-11-15'),
        );

        self::assertSame([$this->idOf($kit, 'P-1')], array_column($found, 'id'));
        self::assertSame('2026-11-12', $found[0]['date']->format('Y-m-d'));
    }

    public function testItIsActiveOnlyWithTheCapabilityAndATreasuryAccountForThatBank(): void
    {
        $kit = $this->kit();

        self::assertTrue($this->source($kit)->activeFor(self::BCP));
        self::assertFalse(
            $this->source($kit)->activeFor('bank-without-account'),
            'the old sources stay until the bank has its account',
        );
        self::assertFalse(
            $this->source($kit, enabled: false)->activeFor(self::BCP),
            'with Treasury off nothing changes',
        );
        self::assertTrue($this->source($kit)->anyBankAccount());
        self::assertFalse($this->source($kit, enabled: false)->anyBankAccount());
    }

    public function testTheListShowsWhatIsStillPendingForBankAccountsOnly(): void
    {
        $kit = $this->kit();
        $kit->account('yape', AccountKind::Wallet);
        $this->move($kit, 'bcp', 'P-1', Direction::Out, '10.00', '2026-11-12');
        $this->move($kit, 'bcp', 'P-2', Direction::Out, '20.00', '2026-11-13');
        $this->move($kit, 'bbva', 'P-3', Direction::In, '30.00', '2026-11-14');
        $this->move($kit, 'yape', 'P-4', Direction::In, '40.00', '2026-11-14');
        $kit->db->insert('bank_reconciliation_match', ['treasury_movement_id' => $this->idOf($kit, 'P-2')]);

        $all = $this->source($kit)->unreconciled(null, null, null, 50);
        $bcpOnly = $this->source($kit)->unreconciled(self::BCP, null, null, 50);

        self::assertEqualsCanonicalizing(
            ['-10.00', '30.00'],
            array_column($all, 'amount'),
            'the wallet and the matched one are left out',
        );
        self::assertSame(['-10.00'], array_column($bcpOnly, 'amount'));
        self::assertSame('Banco bcp', $bcpOnly[0]['account']);
    }

    public function testSuggestionsComeFromTheLinesOwnTreasuryAccountAndMarkAnExactDay(): void
    {
        $kit = $this->kit();
        $this->move($kit, 'bcp', 'P-1', Direction::Out, '118.00', '2026-11-12');
        $this->move($kit, 'bbva', 'P-2', Direction::Out, '118.00', '2026-11-12');
        $service = new BankReconciliationMatchingService(
            $this->createStub(EntityManagerInterface::class),
            $kit->db,
            new CashMovementHydrator($this->createStub(EntityManagerInterface::class), $kit->db),
            $this->source($kit),
        );

        $suggestions = $service->suggestionsFor($this->line(self::BCP, '-118.00', '2026-11-12'));

        self::assertCount(1, $suggestions, 'the other bank account has the same amount but is not offered');
        self::assertSame('treasury_movement', $suggestions[0]->sourceType);
        self::assertSame($this->idOf($kit, 'P-1'), $suggestions[0]->sourceId);
        self::assertSame('EXACT', $suggestions[0]->confidence);
    }

    public function testConfirmingStoresTheMovementIdAndMarksTheLineMatched(): void
    {
        $kit = $this->kit();
        $this->move($kit, 'bcp', 'P-1', Direction::Out, '118.00', '2026-11-12');
        $id = $this->idOf($kit, 'P-1');
        $line = $this->line(self::BCP, '-118.00', '2026-11-12');
        $persisted = [];
        $em = $this->entityManager(null, $persisted);

        $match = new BankReconciliationMatchService($em, $this->source($kit))->confirmTreasuryMatch(
            $line,
            $id,
            new User(),
            'MANUAL',
        );

        self::assertSame($id, $match->getTreasuryMovementId());
        self::assertSame(BankStatementLine::MATCHED, $line->getStatus());
        self::assertCount(1, $persisted);
    }

    public function testAMovementOfAnotherBankAccountCannotBeUsed(): void
    {
        $kit = $this->kit();
        $this->move($kit, 'bbva', 'P-1', Direction::Out, '118.00', '2026-11-12');
        $persisted = [];

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('no pertenece a la cuenta bancaria');
        new BankReconciliationMatchService(
            $this->entityManager(null, $persisted),
            $this->source($kit),
        )->confirmTreasuryMatch(
            $this->line(self::BCP, '-118.00', '2026-11-12'),
            $this->idOf($kit, 'P-1'),
            new User(),
            'MANUAL',
        );
    }

    public function testAMovementAlreadyReconciledWithAnotherLineIsRefused(): void
    {
        $kit = $this->kit();
        $this->move($kit, 'bcp', 'P-1', Direction::Out, '118.00', '2026-11-12');
        $persisted = [];

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('ya fue conciliado');
        new BankReconciliationMatchService(
            $this->entityManager(new BankReconciliationMatch(), $persisted),
            $this->source($kit),
        )->confirmTreasuryMatch(
            $this->line(self::BCP, '-118.00', '2026-11-12'),
            $this->idOf($kit, 'P-1'),
            new User(),
            'MANUAL',
        );
    }

    public function testALineThatIsNotPendingCannotBeMatchedAgain(): void
    {
        $kit = $this->kit();
        $line = $this->line(self::BCP, '-118.00', '2026-11-12')->setStatus(BankStatementLine::MATCHED);
        $persisted = [];

        $this->expectException(UnprocessableEntityHttpException::class);
        new BankReconciliationMatchService(
            $this->entityManager(null, $persisted),
            $this->source($kit),
        )->confirmTreasuryMatch($line, 'any', new User(), 'MANUAL');
    }

    private function kit(): TreasuryTestDatabase
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp', AccountKind::Bank, name: 'Banco bcp', bankAccountId: self::BCP);
        $kit->account('bbva', AccountKind::Bank, name: 'Banco bbva', bankAccountId: self::BBVA);

        return $kit;
    }

    private function source(TreasuryTestDatabase $kit, bool $enabled = true): TreasuryReconciliationSource
    {
        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository
            ->method('allByKey')
            ->willReturn(
                $enabled ? ['treasury.accounts' => new TenantCapability('treasury.accounts')->setEnabled(true)] : [],
            );

        return new TreasuryReconciliationSource(
            $kit->db,
            new TenantCapabilityResolver($this->createStub(FeatureCheckerInterface::class), $repository),
        );
    }

    private function move(
        TreasuryTestDatabase $kit,
        string $account,
        string $key,
        Direction $direction,
        string $amount,
        string $day,
    ): void {
        $kit->recorder->record(new MoneyMovementRequest(
            key: new MovementKey(MovementSource::SupplierPayment, $key),
            direction: $direction,
            amount: $amount,
            currency: 'PEN',
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable($day),
            accountId: $account,
            description: 'Pago ' . $key,
        ));
    }

    private function idOf(TreasuryTestDatabase $kit, string $key): string
    {
        return (string) $kit->db->fetchOne('SELECT id FROM treasury_movement WHERE source_id = ?', [$key]);
    }

    private function line(string $bankAccountId, string $amount, string $day): BankStatementLine
    {
        $bank = new BankAccount();
        new \ReflectionProperty(BankAccount::class, 'id')->setValue($bank, $bankAccountId);

        return new BankStatementLine()
            ->setBankAccount($bank)
            ->setAmount($amount)
            ->setTransactionDate(new DateTimeImmutable($day))
            ->setReference(null);
    }

    /** @param list<object> $persisted */
    private function entityManager(?BankReconciliationMatch $existing, array &$persisted): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        return $em;
    }
}
