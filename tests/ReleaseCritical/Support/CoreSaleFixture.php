<?php

declare(strict_types=1);

namespace App\Tests\ReleaseCritical\Support;

use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Enum\SettlementBehavior;
use App\Contacts\Entity\Customer;
use App\Contacts\Entity\Supplier;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockPolicy;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Settings\Service\TenantSettingsService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class CoreSaleFixture
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantSettingsService $tenantSettings,
    ) {}

    public function seed(string $initialStock = '100.000000'): CoreSaleWorld
    {
        $this->configureTenant();
        $this->ensurePaymentMethods();
        $this->ensureNotaVentaType();
        $this->ensureRefundCategory();

        $suffix = bin2hex(random_bytes(4));

        $company = new Company()
            ->setRuc('20' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT))
            ->setBusinessName('Kernel Core ' . $suffix)
            ->setFiscalAddress('Av. Kernel 123');
        $this->entityManager->persist($company);

        $branch = new Branch()
            ->setName('Sucursal ' . $suffix)
            ->setCompany($company)
            ->setIsActive(true);
        $this->entityManager->persist($branch);

        $warehouse = new Warehouse()
            ->setName('Almacén ' . $suffix)
            ->setBranch($branch)
            ->setIsActive(true);
        $this->entityManager->persist($warehouse);

        $role = $this->entityManager->getRepository(Role::class)->findOneBy([]);
        if (!$role instanceof Role) {
            $role = new Role()->setName('Kernel');
            $this->entityManager->persist($role);
            $this->entityManager->flush();
        }

        $user = new User()
            ->setUsername('kernel_core_' . $suffix)
            ->setPassword('unused')
            ->setRole($role);
        $this->entityManager->persist($user);

        $category = new Category()->setName('Kernel cat ' . $suffix);
        $this->entityManager->persist($category);

        $unit = new UnitOfMeasure()
            ->setName('Unidad ' . $suffix)
            ->setCode('U' . strtoupper($suffix))
            ->setType(1);
        $this->entityManager->persist($unit);

        $product = new Product()
            ->setCode('KCORE-' . $suffix)
            ->setType(1)
            ->setName('Producto kernel ' . $suffix)
            ->setCategory($category)
            ->setUnitOfMeasure($unit)
            ->setPurchasePrice('8.00')
            ->setSalePrice('10.00')
            ->setTrackInventory(true)
            ->setInventoryBehavior(InventoryBehavior::TRACKED);
        $this->entityManager->persist($product);

        $customer = new Customer()
            ->setRuc('10' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT))
            ->setBusinessName('Cliente kernel ' . $suffix)
            ->setFiscalAddress('Av. Cliente 1');
        $this->entityManager->persist($customer);

        $cashRegister = new CashRegister()
            ->setBranch($branch)
            ->setName('Caja ' . $suffix)
            ->setIsActive(true);
        $this->entityManager->persist($cashRegister);

        $now = new DateTimeImmutable('-1 hour');
        $session = new CashSession()
            ->setCashRegister($cashRegister)
            ->setBranch($branch)
            ->setOpenedBy($user)
            ->setStatus('OPEN')
            ->setOpeningAmount('50.00')
            ->setOpenedAt($now)
            ->setCreatedAt($now);
        $this->entityManager->persist($session);

        $this->entityManager->flush();

        $this->entityManager->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, version)
                VALUES (:warehouse_id, :product_id, :qty_on_hand, 0, NOW(), 1)
                ON DUPLICATE KEY UPDATE
                    qty_on_hand = VALUES(qty_on_hand),
                    qty_reserved = 0,
                    updated_at = NOW(),
                    version = version + 1
                SQL,
            [
                'warehouse_id' => (string) $warehouse->getId(),
                'product_id' => (string) $product->getId(),
                'qty_on_hand' => $initialStock,
            ],
        );

        $cashMethod = $this->entityManager->find(PaymentMethod::class, PaymentMethod::CASH);
        $creditMethod = $this->entityManager->find(PaymentMethod::class, PaymentMethod::CREDIT);
        if (!$cashMethod instanceof PaymentMethod || !$creditMethod instanceof PaymentMethod) {
            throw new \RuntimeException('Payment methods 1 (cash) and 2 (credit) are required.');
        }

        return new CoreSaleWorld(
            user: $user,
            branch: $branch,
            warehouse: $warehouse,
            customer: $customer,
            product: $product,
            cashMethod: $cashMethod,
            creditMethod: $creditMethod,
            cashRegister: $cashRegister,
            cashSession: $session,
            initialStock: $initialStock,
        );
    }

    public function onHand(Warehouse $warehouse, Product $product): string
    {
        $qty = $this->entityManager->getConnection()->fetchOne('SELECT qty_on_hand FROM stock_items WHERE warehouse_id = :w AND product_id = :p', [
            'w' => (string) $warehouse->getId(),
            'p' => (string) $product->getId(),
        ]);

        return $qty === false ? '0' : (string) $qty;
    }

    public function extraSupplier(): Supplier
    {
        $suffix = bin2hex(random_bytes(4));
        $supplier = new Supplier()
            ->setRuc('20' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT))
            ->setBusinessName('Proveedor kernel ' . $suffix)
            ->setFiscalAddress('Av. Proveedor 1');
        $this->entityManager->persist($supplier);
        $this->entityManager->flush();

        return $supplier;
    }

    public function extraWarehouse(CoreSaleWorld $world): Warehouse
    {
        $warehouse = new Warehouse()
            ->setName('Almacén dest ' . bin2hex(random_bytes(3)))
            ->setBranch($world->branch)
            ->setIsActive(true);
        $this->entityManager->persist($warehouse);
        $this->entityManager->flush();

        return $warehouse;
    }

    public function closeSession(CashSession $session): void
    {
        $session->setStatus('CLOSED');
        $session->setClosedAt(new DateTimeImmutable());
        $session->setClosedBy($session->getOpenedBy());
        $this->entityManager->flush();
    }

    public function cashMovementCount(string $saleId): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM cash_movements WHERE sale_id = :sale_id AND movement_type = :type', [
            'sale_id' => $saleId,
            'type' => 'SALE_IN',
        ]);
    }

    public function cashRefundCount(string $saleId): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM cash_movements WHERE sale_id = :sale_id AND movement_type = :type', [
            'sale_id' => $saleId,
            'type' => 'REFUND_OUT',
        ]);
    }

    private function configureTenant(): void
    {
        $settings = $this->tenantSettings->get();
        $settings->setRequireCashRegister(true)->setAutoInvoice(false)->setNegativeStockPolicy(StockPolicy::STRICT);
        $this->tenantSettings->save($settings);
    }

    private function ensurePaymentMethods(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO payment_method (id, name, is_active, settlement_behavior)
                VALUES
                    (1, 'Efectivo', 1, :cash),
                    (2, 'Crédito', 1, :credit)
                ON DUPLICATE KEY UPDATE
                    is_active = 1,
                    settlement_behavior = VALUES(settlement_behavior)
                SQL,
            [
                'cash' => SettlementBehavior::COLLECTED->value,
                'credit' => SettlementBehavior::RECEIVABLE->value,
            ],
        );
    }

    private function ensureNotaVentaType(): void
    {
        $existing = $this->entityManager->find(VoucherType::class, '00');
        if ($existing instanceof VoucherType) {
            return;
        }

        $type = new VoucherType()
            ->setCode('00')
            ->setName('Nota de venta')
            ->setIsActive(true);
        $this->entityManager->persist($type);
        $this->entityManager->flush();
    }

    private function ensureRefundCategory(): void
    {
        $this->entityManager->getConnection()->executeStatement(<<<'SQL'
            INSERT INTO cash_movement_category (code, name, type)
            SELECT 'customer_refund', 'Devolución', 'expense'
            WHERE NOT EXISTS (
                SELECT 1 FROM cash_movement_category WHERE code = 'customer_refund'
            )
            SQL);
    }
}
