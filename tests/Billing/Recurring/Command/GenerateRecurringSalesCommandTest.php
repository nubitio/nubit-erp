<?php

declare(strict_types=1);

namespace App\Tests\Billing\Recurring\Command;

use App\Billing\Recurring\Command\GenerateRecurringSalesCommand;
use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\SubscriptionPlan;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Contacts\Entity\Customer;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Settings\Repository\TenantSettingsRepository;
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
    $command = new GenerateRecurringSalesCommand($em, $settings, $connection);

    // logger is normally injected by the container via #[Required] initDependencies();
    // set directly since we bypass PerTenantCommand::execute() (and its tenant-registry
    // wiring) to call executeTenantCommand() straight, exercising only this command's logic.
    $loggerProperty = new \ReflectionProperty(\Nubit\Platform\Symfony\Console\PerTenantCommand::class, 'logger');
    $loggerProperty->setValue($command, $logger);

    return $command;
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
        'SELECT grand_total, balance_due, paid_total, seller_user_id FROM sales WHERE customer_subscription_id = ?',
        [$subscription->getId()],
    );
    $itemCount = (int) $this->connection->fetchOne(
        'SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.customer_subscription_id = ?',
        [$subscription->getId()],
    );

    expect($sale)->not->toBeFalse()
        ->and($sale['grand_total'])->toBe('49.90')
        ->and($sale['balance_due'])->toBe('49.90')
        ->and($sale['paid_total'])->toBe('0.00')
        ->and((int) $sale['seller_user_id'])->toBe($adminId)
        ->and($itemCount)->toBe(1);

    $nextBillingDate = $this->connection->fetchOne(
        'SELECT next_billing_date FROM customer_subscription WHERE id = ?',
        [$subscription->getId()],
    );
    expect($nextBillingDate)->toBe($today->modify('+1 month')->format('Y-m-d'));
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

it('fails when no admin/super-admin user exists to attribute the sale to', function (): void {
    $plan = makeSubscriptionPlan($this->em, '49.90');
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    makeCustomerSubscription($this->em, $plan, $today->modify('-1 day'));
    $this->em->flush();

    $command = newCommand($this->em, static::getContainer()->get(TenantSettingsRepository::class), $this->connection, static::getContainer()->get('logger'));
    $exitCode = runTenantCommand($command);

    expect($exitCode)->toBe(1);
});
