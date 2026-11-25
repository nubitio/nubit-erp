<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\Accounting\Entity\Account;
use App\Accounting\Message\EconomicEvent;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Integration\Entity\ApiClient;
use App\Sales\Domain\IntegrationSaleActor;
use App\Sales\Port\ConfirmIntegrationCredit;
use App\Sales\Service\ExternalBankCollection;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Repository\GatewayRouteRepository;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Service\TreasuryActivation;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final class ExternalBankCollectionTest extends KernelTestCase
{
    /** @return iterable<string, array{bool, bool, bool}> */
    public static function treasuryModes(): iterable
    {
        yield 'bank receipt and duplicate' => [true, false, true];
        yield 'gateway receipt and duplicate' => [true, true, true];
        yield 'invalid gateway ledger rolls back' => [true, true, false];
        yield 'Treasury unavailable rolls back' => [false, false, true];
    }

    #[DataProvider('treasuryModes')]
    public function testCollectionDoesNotNeedADrawerAndCommitsOnlyWhenTreasuryCanPost(
        bool $enabled,
        bool $gateway,
        bool $validLedger,
    ): void {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $db = $em->getConnection();
        if (!$db->createSchemaManager()->tablesExist(['integration_sales_request', 'treasury_gateway_route'])) {
            static::markTestSkipped('Run against a migrated test tenant.');
        }
        $settings = $container->get(TenantSettingsService::class);
        static::assertInstanceOf(TenantSettingsService::class, $settings);
        $fixture = new CoreSaleFixture($em, $settings);
        $world = $fixture->seed('3.000000');
        $fixture->closeSession($world->cashSession);
        $branch = (string) $world->branch->getId();
        $client = new ApiClient('External collector', ['sales:write']);
        $method = new PaymentMethod()
            ->setName('External ' . bin2hex(random_bytes(4)))
            ->setSettlementBehavior(SettlementBehavior::COLLECTED)
            ->setIsActive(true);
        $em->persist($client);
        $em->persist($method);
        $em->flush();
        $actor = new IntegrationSaleActor((string) $client->getId());

        $confirm = $container->get(ConfirmIntegrationCredit::class);
        static::assertInstanceOf(ConfirmIntegrationCredit::class, $confirm);
        $saleId = $db->transactional(static fn(): string => $confirm->confirmIntegrationCredit(
            $actor->clientId,
            $branch,
            (string) $world->warehouse->getId(),
            (int) $world->customer->getId(),
            [['product' => (string) $world->product->getId(), 'qty' => '2', 'unitPrice' => '10.00']],
            '20.00',
        ));

        $activation = $container->get(TreasuryActivation::class);
        $accounts = $container->get(TreasuryAccountRepository::class);
        $service = $container->get(ExternalBankCollection::class);
        static::assertInstanceOf(TreasuryActivation::class, $activation);
        static::assertInstanceOf(TreasuryAccountRepository::class, $accounts);
        static::assertInstanceOf(ExternalBankCollection::class, $service);
        $activation->set($enabled);
        $accountId = Uuid::v7()->toRfc4122();
        $accounts->add(
            new TreasuryAccount(
                $accountId,
                $gateway ? AccountKind::Clearing : AccountKind::Bank,
                'External account',
                'PEN',
                $branch,
                null,
                null,
                true,
            ),
        );
        $methodId = (int) $method->getId();
        $ledgerCode = 'G' . bin2hex(random_bytes(4));
        if ($gateway) {
            $routes = $container->get(GatewayRouteRepository::class);
            static::assertInstanceOf(GatewayRouteRepository::class, $routes);
            $routes->save($branch, $methodId, 'PEN', $accountId, $ledgerCode);
            if ($validLedger) {
                $em->persist(
                    new Account()
                        ->setCode($ledgerCode)
                        ->setName('Gateway funds'),
                );
                $em->flush();
            }
        } else {
            $accounts->map($branch, $methodId, 'PEN', $accountId);
        }
        $commits = $enabled && $validLedger;

        $post = static fn(
            bool $replay = false,
            string $amount = '8.00',
            ?bool $destination = null,
        ): int => $service->post(
            $saleId,
            $actor,
            $branch,
            $methodId,
            $amount,
            'PEN',
            new DateTimeImmutable('2026-11-24'),
            'bank-ref-1',
            allowReplay: $replay,
            gateway: $destination ?? $gateway,
        );
        if ($commits) {
            $paymentId = $post();
            static::assertGreaterThan(0, $paymentId);
            static::assertSame($paymentId, $post(replay: true));
            $debit = $db->fetchOne(
                'SELECT l.debit FROM journal_entry j JOIN journal_entry_line l ON l.journal_entry_id = j.id JOIN chart_of_accounts a ON a.id = l.account_id WHERE j.source = ? AND j.source_id = ? AND a.code = ?',
                [EconomicEvent::SOURCE_RECEIVABLE_COLLECT, (string) $paymentId, $gateway ? $ledgerCode : '1041'],
            );
            static::assertSame('8.00', $debit);
        }
        try {
            $post(replay: true, destination: $gateway && !$commits, amount: $gateway && $commits ? '8.00' : '7.00');
            static::fail('A duplicate receipt or a receipt with Treasury disabled must be rejected.');
        } catch (ConflictHttpException|UnprocessableEntityHttpException $e) {
            static::assertSame($commits ? 409 : 422, $e->getStatusCode());
        }

        static::assertSame(
            $commits ? '12.00' : '20.00',
            $db->fetchOne('SELECT balance_due FROM sales WHERE id = ?', [$saleId]),
        );
        static::assertSame(
            $commits ? 1 : 0,
            (int) $db->fetchOne('SELECT COUNT(*) FROM sale_payments WHERE sale_id = ?', [$saleId]),
        );
        static::assertSame(
            $commits ? 1 : 0,
            (int) $db->fetchOne('SELECT COUNT(*) FROM treasury_movement WHERE account_id = ?', [$accountId]),
        );
        static::assertSame(0, $fixture->cashMovementCount($saleId));
    }
}
