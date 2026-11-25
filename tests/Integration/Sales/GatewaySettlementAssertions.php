<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\BankReconciliation\Entity\BankAccount;
use App\BankReconciliation\Service\TreasuryReconciliationSource;
use App\Efact\Domain\Money;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\GatewaySettlement;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use App\Treasury\Service\SettleGateway;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final class GatewaySettlementAssertions
{
    public static function verify(
        ContainerInterface $container,
        EntityManagerInterface $em,
        string $clearingId,
        string $branch,
    ): void {
        $db = $em->getConnection();
        $accounts = $container->get(TreasuryAccountRepository::class);
        $movements = $container->get(TreasuryMovementRepository::class);
        $service = $container->get(SettleGateway::class);
        Assert::assertInstanceOf(TreasuryAccountRepository::class, $accounts);
        Assert::assertInstanceOf(TreasuryMovementRepository::class, $movements);
        Assert::assertInstanceOf(SettleGateway::class, $service);
        $bank = new BankAccount()
            ->setBank('Pilot')
            ->setAlias('Payouts')
            ->setAccountNumber('0000123456');
        $em->persist($bank);
        $em->flush();
        $bankId = Uuid::v7()->toRfc4122();
        $accounts->add(
            new TreasuryAccount($bankId, AccountKind::Bank, 'Payouts', 'PEN', $branch, null, $bank->getId(), true),
        );
        $capture = (string) $db->fetchOne('SELECT id FROM treasury_movement WHERE account_id = ?', [$clearingId]);
        $date = new DateTimeImmutable('2026-11-25');
        $settlement = static fn(string $ref, int $net): GatewaySettlement => new GatewaySettlement(
            $clearingId,
            $bankId,
            $ref,
            $net,
            50,
            $date,
            '659',
            [$capture],
        );
        try {
            $service->settle($settlement('bad-total', 740));
            Assert::fail('Mismatched payout total must be rejected.');
        } catch (TreasuryException $e) {
            Assert::assertStringContainsString('deben sumar', $e->getMessage());
        }
        Assert::assertSame('8.00', $movements->balance($clearingId));
        Assert::assertSame('0.00', $movements->balance($bankId));

        $receipt = $service->settle($settlement('payout', 750));
        Assert::assertSame($receipt, $service->settle($settlement('payout', 750)));
        Assert::assertSame('0.00', $movements->balance($clearingId));
        Assert::assertSame('7.50', $movements->balance($bankId));
        $reconciliation = $container->get(TreasuryReconciliationSource::class);
        Assert::assertInstanceOf(TreasuryReconciliationSource::class, $reconciliation);
        $candidates = $reconciliation->candidates((string) $bank->getId(), Money::money('7.50'), $date, $date);
        Assert::assertCount(1, $candidates);
        Assert::assertStringContainsString('payout', $candidates[0]['label']);
        $lines = $db->fetchAllAssociative(
            'SELECT a.code, l.debit, l.credit FROM journal_entry j JOIN journal_entry_line l ON l.journal_entry_id = j.id JOIN chart_of_accounts a ON a.id = l.account_id WHERE j.source = ? AND j.source_id = ? ORDER BY l.line_no',
            ['gateway_settlement', $receipt],
        );
        Assert::assertSame(['7.50', '0.50', '0.00'], array_column($lines, 'debit'));
        Assert::assertSame(['0.00', '0.00', '8.00'], array_column($lines, 'credit'));
        Assert::assertSame(['1041', '659'], array_slice(array_column($lines, 'code'), 0, 2));

        foreach (['payout' => 749, 'another-payout' => 750] as $reference => $net) {
            try {
                $service->settle($settlement($reference, $net));
                Assert::fail('A payout conflict or reused capture must be rejected.');
            } catch (TreasuryException $e) {
                Assert::assertStringContainsString(
                    $reference === 'payout' ? 'otros datos' : 'ya liquidadas',
                    $e->getMessage(),
                );
            }
        }
        Assert::assertSame(
            2,
            (int) $db->fetchOne('SELECT COUNT(*) FROM treasury_movement WHERE source_id = ?', [$receipt]),
        );
    }
}
