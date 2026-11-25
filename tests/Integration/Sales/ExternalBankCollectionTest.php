<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Integration\Entity\ApiClient;
use App\Sales\Domain\IntegrationSaleActor;
use App\Sales\Port\ConfirmIntegrationCredit;
use App\Sales\Service\ExternalBankCollection;
use App\Sales\Service\SalePaymentService;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Port\RecordsMoneyMovement;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Service\TreasuryActivation;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class ExternalBankCollectionTest extends KernelTestCase
{
    /** @return iterable<string, array{bool}> */
    public static function treasuryModes(): iterable
    {
        yield 'bank receipt and duplicate' => [true];
        yield 'Treasury unavailable rolls back' => [false];
    }

    #[DataProvider('treasuryModes')]
    public function testCollectionDoesNotNeedADrawerAndCommitsOnlyWhenTreasuryCanPost(bool $enabled): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $db = $em->getConnection();
        if (!$db->createSchemaManager()->tablesExist(['integration_sales_request', 'treasury_account'])) {
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
        $payments = $container->get(SalePaymentService::class);
        $treasury = $container->get(RecordsMoneyMovement::class);
        $bus = $container->get(MessageBusInterface::class);
        static::assertInstanceOf(TreasuryActivation::class, $activation);
        static::assertInstanceOf(TreasuryAccountRepository::class, $accounts);
        static::assertInstanceOf(SalePaymentService::class, $payments);
        static::assertInstanceOf(RecordsMoneyMovement::class, $treasury);
        static::assertInstanceOf(MessageBusInterface::class, $bus);
        $service = new ExternalBankCollection($em, $payments, $treasury, $bus);
        $activation->set($enabled);
        $accountId = Uuid::v7()->toRfc4122();
        $accounts->add(
            new TreasuryAccount($accountId, AccountKind::Bank, 'External bank', 'PEN', $branch, null, null, true),
        );
        $methodId = (int) $method->getId();
        $accounts->map($branch, $methodId, 'PEN', $accountId);

        $post = static fn(bool $replay = false, string $amount = '8.00'): int => $service->post(
            $saleId,
            $actor,
            $branch,
            $methodId,
            $amount,
            'PEN',
            new DateTimeImmutable('2026-11-24'),
            'bank-ref-1',
            allowReplay: $replay,
        );
        if ($enabled) {
            $paymentId = $post();
            static::assertGreaterThan(0, $paymentId);
            static::assertSame($paymentId, $post(replay: true));
        }
        try {
            $post(replay: true, amount: '7.00');
            static::fail('A duplicate receipt or a receipt with Treasury disabled must be rejected.');
        } catch (ConflictHttpException|UnprocessableEntityHttpException $e) {
            static::assertSame($enabled ? 409 : 422, $e->getStatusCode());
        }

        static::assertSame(
            $enabled ? '12.00' : '20.00',
            $db->fetchOne('SELECT balance_due FROM sales WHERE id = ?', [$saleId]),
        );
        static::assertSame(
            $enabled ? 1 : 0,
            (int) $db->fetchOne('SELECT COUNT(*) FROM sale_payments WHERE sale_id = ?', [$saleId]),
        );
        static::assertSame(
            $enabled ? 1 : 0,
            (int) $db->fetchOne('SELECT COUNT(*) FROM treasury_movement WHERE account_id = ?', [$accountId]),
        );
        static::assertSame(0, $fixture->cashMovementCount($saleId));
    }
}
