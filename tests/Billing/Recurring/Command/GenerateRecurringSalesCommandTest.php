<?php

declare(strict_types=1);

namespace App\Tests\Billing\Recurring\Command;

use App\Billing\Recurring\Command\GenerateRecurringSalesCommand;
use App\Billing\Recurring\Dto\GenerateSubscriptionChargeDto;
use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\CustomerSubscriptionLine;
use App\Billing\Recurring\Entity\CustomerSubscriptionRun;
use App\Billing\Recurring\Entity\SubscriptionPlan;
use App\Billing\Recurring\Repository\CustomerSubscriptionLineRepository;
use App\Billing\Recurring\Service\SubscriptionChargeGenerator;
use App\Billing\Recurring\State\GenerateSubscriptionChargeProcessor;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Contacts\Entity\Customer;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Settings\Repository\TenantSettingsRepository;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration test for GenerateRecurringSalesCommand: real EntityManager
 * against the demo_test DB, each test wrapped in a rolled-back transaction
 * (DAMA\DoctrineTestBundle). Calls executeTenantCommand() directly via
 * reflection to exercise the actual persistence logic without pulling in
 * PerTenantCommand's tenant-registry/connection-switching machinery, which
 * is a cross-cutting concern of the base class, not of this command.
 */
uses(KernelTestCase::class);

function makeAdminUser(Connection $connection): int
{
    // rol_id must be explicitly 1 or 2 (super-admin/admin) per
    // resolveSystemSellerId()'s WHERE rol_id IN (1, 2) — can't rely on
    // auto-increment, since AUTO_INCREMENT counters aren't transactional
    // in InnoDB and drift upward across rolled-back test runs.
    $connection->executeStatement(
        'INSERT INTO rol (rol_id, descripcion) VALUES (2, ?) ON DUPLICATE KEY UPDATE descripcion = descripcion',
        ['Admin'],
    );

    $connection->insert('usuario', [
        'rol_id' => 2,
        'username' => 'recurring-test-admin-' . bin2hex(random_bytes(4)),
        'password' => 'x',
    ]);

    return (int) $connection->lastInsertId();
}

function makeSubscriptionPlan(EntityManagerInterface $em, string $amount, string $billingCycle = 'MONTHLY'): SubscriptionPlan
{
    $unit = new UnitOfMeasure();
    $unit->setName('Unidad')->setCode('UND')->setType(1);
    $em->persist($unit);

    $category = new Category();
    $category->setName('Servicios recurrentes');
    $em->persist($category);

    $product = new Product();
    $product->setName('Hosting mensual')
        ->setCategory($category)
        ->setUnitOfMeasure($unit)
        ->setSalePrice($amount);
    $em->persist($product);

    $company = new Company();
    $company->setRuc('20123456789')
        ->setBusinessName('Empresa de prueba SAC')
        ->setFiscalAddress('Av. Test 123');
    $em->persist($company);

    $branch = new Branch();
    $branch->setName('Sucursal principal')->setCompany($company);
    $em->persist($branch);

    $warehouse = new Warehouse();
    $warehouse->setName('Almacen principal')->setBranch($branch);
    $em->persist($warehouse);

    $plan = new SubscriptionPlan();
    $plan->setName('Plan mensual')
        ->setProduct($product)
        ->setBillingCycle($billingCycle)
        ->setAmount($amount)
        ->setBranch($branch)
        ->setWarehouse($warehouse);
    $em->persist($plan);

    return $plan;
}

function makeCustomerSubscription(
    EntityManagerInterface $em,
    SubscriptionPlan $plan,
    \DateTimeImmutable $nextBillingDate,
    string $status = 'active',
): CustomerSubscription {
    $customer = new Customer();
    $customer->setRuc((string) random_int(10000000, 99999999))
        ->setBusinessName('Cliente de prueba');
    $em->persist($customer);

    $subscription = new CustomerSubscription();
    $subscription->setCustomer($customer)
        ->setPlan($plan)
        ->setStatus($status)
        ->setStartDate($nextBillingDate)
        ->setNextBillingDate($nextBillingDate);
    $em->persist($subscription);

    return $subscription;
}

function newCommand(
    EntityManagerInterface $em,
    TenantSettingsRepository $settings,
    Connection $connection,
    \Psr\Log\LoggerInterface $logger,
): GenerateRecurringSalesCommand {
    $generator = newSubscriptionChargeGenerator($em, $settings);
    $command = new GenerateRecurringSalesCommand($em, $connection, $generator);

    // logger is normally injected by the container via #[Required] initDependencies();
    // set directly since we bypass PerTenantCommand::execute() (and its tenant-registry
    // wiring) to call executeTenantCommand() straight, exercising only this command's logic.
    $loggerProperty = new \ReflectionProperty(\Nubit\Platform\Symfony\Console\PerTenantCommand::class, 'logger');
    $loggerProperty->setValue($command, $logger);

    return $command;
}

function newSubscriptionChargeGenerator(
    EntityManagerInterface $em,
    TenantSettingsRepository $settings,
): SubscriptionChargeGenerator {
    /** @var \App\Billing\Recurring\Repository\CustomerSubscriptionRunRepository $runRepository */
    $runRepository = $em->getRepository(CustomerSubscriptionRun::class);
    /** @var CustomerSubscriptionLineRepository $lineRepository */
    $lineRepository = $em->getRepository(\App\Billing\Recurring\Entity\CustomerSubscriptionLine::class);

    return new SubscriptionChargeGenerator($em, $settings, $runRepository, $lineRepository);
}

function runTenantCommand(GenerateRecurringSalesCommand $command): int
{
    $ref = new \ReflectionMethod($command, 'executeTenantCommand');

    return $ref->invoke($command, new ArrayInput([]), new BufferedOutput());
}

beforeEach(function (): void {
    self::bootKernel();
    $this->em = static::getContainer()->get(EntityManagerInterface::class);
    $this->connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
});

it('generates a sale for a subscription whose billing date is due', function (): void {
    $adminId = makeAdminUser($this->connection);
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $subscription = makeCustomerSubscription($this->em, $plan, $today->modify('-1 day'));
    $this->em->flush();

    $command = newCommand($this->em, static::getContainer()->get(TenantSettingsRepository::class), $this->connection, static::getContainer()->get('logger'));
    $exitCode = runTenantCommand($command);

    expect($exitCode)->toBe(0);

    // Queried as scalars via raw SQL rather than hydrated entities: asserting
    // directly on a Doctrine entity blows PHPUnit's failure-message exporter
    // (it walks the full object graph, including the EntityManager reachable
    // through lazy-loaded associations) if the assertion ever fails.
    $sale = $this->connection->fetchAssociative(
        'SELECT grand_total, balance_due, paid_total, seller_user_id, note FROM sales WHERE customer_subscription_id = ?',
        [$subscription->getId()],
    );
    $item = $this->connection->fetchAssociative(
        'SELECT si.name_snapshot, si.unit_price FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.customer_subscription_id = ?',
        [$subscription->getId()],
    );
    $itemCount = (int) $this->connection->fetchOne(
        'SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.customer_subscription_id = ?',
        [$subscription->getId()],
    );
    $runCount = (int) $this->connection->fetchOne(
        'SELECT COUNT(*) FROM customer_subscription_run WHERE subscription_id = ?',
        [$subscription->getId()],
    );

    expect($sale)->not->toBeFalse()
        ->and($sale['grand_total'])->toBe('49.90')
        ->and($sale['balance_due'])->toBe('49.90')
        ->and($sale['paid_total'])->toBe('0.00')
        ->and($sale['note'])->toBe('Cargo recurrente: Plan mensual')
        ->and((int) $sale['seller_user_id'])->toBe($adminId)
        ->and($item['name_snapshot'])->toContain('Hosting mensual')
        ->and($item['name_snapshot'])->toContain('Periodo')
        ->and($item['unit_price'])->toBe('49.9000')
        ->and($itemCount)->toBe(1)
        ->and($runCount)->toBe(1);

    $nextBillingDate = $this->connection->fetchOne(
        'SELECT next_billing_date FROM customer_subscription WHERE id = ?',
        [$subscription->getId()],
    );
    expect($nextBillingDate)->toBe($today->modify('-1 day')->modify('+1 month')->format('Y-m-d'));
});

it('generates all configured subscription lines and sums them exactly', function (): void {
    makeAdminUser($this->connection);
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $subscription = makeCustomerSubscription($this->em, $plan, $today);

    $lineOne = (new CustomerSubscriptionLine())
        ->setSubscription($subscription)
        ->setProduct($plan->getProduct())
        ->setDescription('Hosting Docker')
        ->setQuantity('1.000')
        ->setUnitPrice('100.0000')
        ->setSortOrder(1);
    $lineTwo = (new CustomerSubscriptionLine())
        ->setSubscription($subscription)
        ->setProduct($plan->getProduct())
        ->setDescription('Soporte administrado')
        ->setQuantity('2.000')
        ->setUnitPrice('25.5000')
        ->setSortOrder(2);
    $this->em->persist($lineOne);
    $this->em->persist($lineTwo);
    $this->em->flush();

    $command = newCommand($this->em, static::getContainer()->get(TenantSettingsRepository::class), $this->connection, static::getContainer()->get('logger'));
    expect(runTenantCommand($command))->toBe(0);

    $sale = $this->connection->fetchAssociative(
        'SELECT id, grand_total FROM sales WHERE customer_subscription_id = ?',
        [$subscription->getId()],
    );
    $items = $this->connection->fetchAllAssociative(
        'SELECT name_snapshot, qty, unit_price, net_amount FROM sale_items WHERE sale_id = ? ORDER BY line_no',
        [$sale['id']],
    );

    expect($sale['grand_total'])->toBe('151.00')
        ->and($items)->toHaveCount(2)
        ->and($items[0]['name_snapshot'])->toContain('Hosting Docker')
        ->and($items[0]['net_amount'])->toBe('100.00')
        ->and($items[1]['name_snapshot'])->toContain('Soporte administrado')
        ->and($items[1]['net_amount'])->toBe('51.00');
});

it('uses the subscription description, service reference and agreed amount', function (): void {
    makeAdminUser($this->connection);
    $plan = makeSubscriptionPlan($this->em, '49.90', 'ANNUAL');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $subscription = makeCustomerSubscription($this->em, $plan, $today);
    $subscription
        ->setCustomDescription('Hosting Docker administrado')
        ->setServiceReference('example.com')
        ->setAgreedAmount('450.00');
    $this->em->flush();

    $command = newCommand($this->em, static::getContainer()->get(TenantSettingsRepository::class), $this->connection, static::getContainer()->get('logger'));
    expect(runTenantCommand($command))->toBe(0);

    $item = $this->connection->fetchAssociative(
        'SELECT si.name_snapshot, si.unit_price FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.customer_subscription_id = ?',
        [$subscription->getId()],
    );
    $total = $this->connection->fetchOne(
        'SELECT grand_total FROM sales WHERE customer_subscription_id = ?',
        [$subscription->getId()],
    );

    expect($item)->not->toBeFalse()
        ->and($item['name_snapshot'])->toContain('Hosting Docker administrado - example.com')
        ->and($item['name_snapshot'])->toContain($today->format('d/m/Y'))
        ->and($item['unit_price'])->toBe('450.0000')
        ->and($total)->toBe('450.00');
});

it('does not generate a sale for a subscription that is not yet due', function (): void {
    makeAdminUser($this->connection);
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $subscription = makeCustomerSubscription($this->em, $plan, $today->modify('+5 days'));
    $this->em->flush();

    $command = newCommand($this->em, static::getContainer()->get(TenantSettingsRepository::class), $this->connection, static::getContainer()->get('logger'));
    $exitCode = runTenantCommand($command);

    expect($exitCode)->toBe(0);

    $saleCount = (int) $this->connection->fetchOne(
        'SELECT COUNT(*) FROM sales WHERE customer_subscription_id = ?',
        [$subscription->getId()],
    );
    expect($saleCount)->toBe(0);
});

it('manual generation rejects a future billing period', function (): void {
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $subscription = makeCustomerSubscription($this->em, $plan, $today->modify('+5 days'));
    $this->em->flush();

    $security = new class implements Security {
        public function isAuthenticated(): bool { return true; }
        public function connectedUser(): ?AuthUserDto { return new AuthUserDto(99, 'tester'); }
        public function hasPermission(string $permission): bool { return $permission === 'subscriptions.generate'; }
    };
    $processor = new GenerateSubscriptionChargeProcessor(
        $this->em,
        $security,
        newSubscriptionChargeGenerator($this->em, static::getContainer()->get(TenantSettingsRepository::class)),
    );

    expect(fn () => $processor->process(
        new GenerateSubscriptionChargeDto((string) $subscription->getId()),
        new Post(),
    ))->toThrow(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
});

it('manual generation requires subscriptions.generate permission', function (): void {
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $subscription = makeCustomerSubscription($this->em, $plan, $today);
    $this->em->flush();

    $security = new class implements Security {
        public function isAuthenticated(): bool { return true; }
        public function connectedUser(): ?AuthUserDto { return new AuthUserDto(99, 'tester'); }
        public function hasPermission(string $permission): bool { return false; }
    };
    $processor = new GenerateSubscriptionChargeProcessor(
        $this->em,
        $security,
        newSubscriptionChargeGenerator($this->em, static::getContainer()->get(TenantSettingsRepository::class)),
    );

    expect(fn () => $processor->process(
        new GenerateSubscriptionChargeDto((string) $subscription->getId()),
        new Post(),
    ))->toThrow(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
});

it('fails when no admin/super-admin user exists to attribute the sale to', function (): void {
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    makeCustomerSubscription($this->em, $plan, $today->modify('-1 day'));
    $this->em->flush();

    $command = newCommand($this->em, static::getContainer()->get(TenantSettingsRepository::class), $this->connection, static::getContainer()->get('logger'));
    $exitCode = runTenantCommand($command);

    expect($exitCode)->toBe(1);
});
