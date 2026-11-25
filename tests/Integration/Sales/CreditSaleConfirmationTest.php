<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sales;

use App\Integration\Entity\ApiClient;
use App\Integration\Sales\SalesClientAccess;
use App\Integration\Sales\SalesIntake;
use App\Integration\Security\ApiClientUser;
use App\Sales\Port\ConfirmIntegrationCredit;
use App\Sales\Repository\CustomerAccountEntryRepository;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreditSaleConfirmationTest extends KernelTestCase
{
    public function testCreditSaleDeductsStockAndCreatesOneReceivableWithoutAHumanOrDrawer(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $db = $em->getConnection();
        if (!$db->createSchemaManager()->tablesExist(['integration_sales_request'])) {
            static::markTestSkipped('Run with a migrated test tenant.');
        }
        $settings = $container->get(TenantSettingsService::class);
        static::assertInstanceOf(TenantSettingsService::class, $settings);
        $fixture = new CoreSaleFixture($em, $settings);
        $world = $fixture->seed('3.000000');
        $fixture->closeSession($world->cashSession);
        $branch = (string) $world->branch->getId();

        $client = new ApiClient('Pilot', ['sales:read', 'sales:write']);
        $client->assignSalesBranch($branch);
        $em->persist($client);
        $em->flush();

        $context = new TenantContext();
        $context->setTenant(1, 'tenant-test', null, 'sales-test');
        $principal = new ApiClientUser(
            (string) $client->getId(),
            'Pilot',
            'tenant-test',
            ['sales:read', 'sales:write'],
        );
        $access = new SalesClientAccess($db, $context);
        $intake = new SalesIntake($db, $access);
        $confirm = $container->get(ConfirmIntegrationCredit::class);
        static::assertInstanceOf(ConfirmIntegrationCredit::class, $confirm);
        $command = static fn(string $clientId, string $branchId): string => $confirm->confirmIntegrationCredit(
            $clientId,
            $branchId,
            (string) $world->warehouse->getId(),
            (int) $world->customer->getId(),
            [['product' => (string) $world->product->getId(), 'qty' => '2', 'unitPrice' => '10.00']],
            '20.00',
        );

        $hash = hash('sha256', 'sample credit sale');
        $created = $intake->submit($principal, $branch, 'sale-one', $hash, $command);
        $retried = $intake->submit($principal, $branch, 'sale-one', $hash, $command);
        static::assertSame(
            [
                'sale_id' => $created['sale_id'],
                'status' => 'COMPLETED',
                'currency' => 'PEN',
                'total' => '20.00',
                'balance_due' => '20.00',
            ],
            $access->findSale($principal, $created['sale_id']),
        );
        $otherClient = new ApiClient('Other pilot', ['sales:read']);
        $otherClient->assignSalesBranch($branch);
        $em->persist($otherClient);
        $em->flush();
        $otherPrincipal = new ApiClientUser(
            (string) $otherClient->getId(),
            'Other pilot',
            'tenant-test',
            ['sales:read'],
        );
        static::assertNull($access->findSale($otherPrincipal, $created['sale_id']));
        static::assertFalse($created['replayed']);
        static::assertSame(['sale_id' => $created['sale_id'], 'replayed' => true], $retried);
        static::assertEqualsWithDelta(1.0, (float) $fixture->onHand($world->warehouse, $world->product), 0.001);
        $row = $db->fetchAssociative('SELECT seller_user_id, integration_client_id, cash_register_id, cash_session_id, balance_due FROM sales WHERE id = ?', [
            $created['sale_id'],
        ]);
        static::assertSame(
            [
                'seller_user_id' => null,
                'integration_client_id' => $client->getId(),
                'cash_register_id' => null,
                'cash_session_id' => null,
                'balance_due' => '20.00',
            ],
            $row,
        );
        static::assertSame(0, $fixture->cashMovementCount($created['sale_id']));
        $entries = $container->get(CustomerAccountEntryRepository::class);
        static::assertInstanceOf(CustomerAccountEntryRepository::class, $entries);
        static::assertTrue($entries->hasChargeForSale($created['sale_id']));

        try {
            $intake->submit($principal, $branch, 'sale-two', $hash, $command);
            static::fail('A second sale cannot overdraw stock.');
        } catch (ServiceException $e) {
            static::assertStringContainsString('Stock disponible insuficiente', $e->getMessage());
            static::assertFalse($db->fetchOne('SELECT 1 FROM integration_sales_request WHERE client_id = ? AND idempotency_key = ?', [
                $client->getId(),
                'sale-two',
            ]));
        }
    }
}
