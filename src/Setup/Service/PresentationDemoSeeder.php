<?php

declare(strict_types=1);

namespace App\Setup\Service;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use App\AccountsPayable\Entity\ExpenseCategory;
use App\AccountsPayable\Entity\SupplierBill;
use App\BankReconciliation\Entity\BankAccount;
use App\Billing\Entity\TaxDocument;
use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\CustomerSubscriptionLine;
use App\Billing\Recurring\Entity\SubscriptionPlan;
use App\Billing\Recurring\Enum\BillingCycle;
use App\Billing\Service\InvoiceCalculator;
use App\Billing\Service\InvoiceLineInput;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Contacts\Entity\Customer;
use App\Contacts\Entity\Supplier;
use App\FixedAssets\Entity\FixedAsset;
use App\Identity\Entity\User;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\ProductKind;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Payroll\Entity\AfpRate;
use App\Payroll\Entity\Employee;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollCalculationService;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\Entity\PurchaseOrderItem;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleQuoteItem;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\DashboardRepository;
use App\Settings\Service\TenantSettingsService;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class PresentationDemoSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly DashboardRepository $dashboardRepository,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly PayrollCalculationService $payrollCalculationService,
        private readonly DefaultPostingService $postingService,
    ) {}

    /**
     * @return array<string, int>
     */
    public function seed(User $demoUser): array
    {
        $warehouse = $this->resolvePresentationWarehouse();
        $branch = $warehouse->getBranch();
        if (!$branch instanceof Branch) {
            throw new RuntimeException('The warehouse has no branch. Run initial setup first.');
        }
        $this->hideSyntheticBranches($branch);

        $company = $this->entityManager->getRepository(Company::class)->findOneBy([]);
        if (!$company instanceof Company) {
            throw new RuntimeException('No company found. Run initial setup first.');
        }

        $unitRepo = $this->entityManager->getRepository(UnitOfMeasure::class);
        $unit = $unitRepo->findOneBy(['code' => 'NIU']) ?? $unitRepo->findOneBy([]);
        if (!$unit instanceof UnitOfMeasure) {
            throw new RuntimeException('No unit of measure found. Run initial setup first.');
        }

        $categories = $this->seedCategories();
        $products = $this->seedProducts($categories, $unit, $warehouse);
        $customers = $this->seedCustomers();
        $subscriptionCount = $this->seedHostingSubscription(
            $branch,
            $warehouse,
            $categories['Tecnología'],
            $unit,
            $customers[0],
        );
        $suppliers = $this->seedSuppliers();
        $paymentMethods = $this->indexPaymentMethods();

        $sales = $this->seedSales($demoUser, $branch, $warehouse, $customers, $products, $paymentMethods);
        $purchases = $this->seedPurchases($demoUser, $branch, $warehouse, $suppliers, $products);
        $this->seedPurchaseOrders($branch, $warehouse, $suppliers, $products);
        $this->seedQuotes($demoUser, $branch, $warehouse, $customers, $products);
        $this->seedEmployeesAndPayroll($company);
        $this->seedTreasuryMasters();
        $this->postAccounting($sales, $purchases);
        $this->finalizePresentation($demoUser, $branch);

        return [
            'customers' => count($customers),
            'products' => count($products) + 1,
            'subscriptions' => $subscriptionCount,
            'suppliers' => count($suppliers),
            'sales' => count($sales),
            'purchases' => count($purchases),
            'employees' => count(PresentationDemoCatalog::employees()),
        ];
    }

    private function resolvePresentationWarehouse(): Warehouse
    {
        $warehouses = $this->entityManager->getRepository(Warehouse::class)->findAll();
        if ($warehouses === []) {
            throw new RuntimeException('No warehouse found. Run initial setup first.');
        }

        $preferred = null;
        foreach ($warehouses as $warehouse) {
            $name = $warehouse->getBranch()?->getName() ?? '';
            if (stripos($name, 'E2E') !== false || str_starts_with($name, 'CI ')) {
                continue;
            }
            if (strcasecmp($name, 'Nova Center') === 0) {
                return $warehouse;
            }
            $preferred ??= $warehouse;
        }

        if ($preferred instanceof Warehouse) {
            return $preferred;
        }

        $first = $warehouses[0];
        if (!$first instanceof Warehouse) {
            throw new RuntimeException('No warehouse found. Run initial setup first.');
        }

        return $first;
    }

    private function hideSyntheticBranches(Branch $keep): void
    {
        foreach ($this->entityManager->getRepository(Branch::class)->findAll() as $branch) {
            if ($branch->getId() === $keep->getId()) {
                continue;
            }
            $name = $branch->getName() ?? '';
            if (stripos($name, 'E2E') !== false || str_starts_with($name, 'CI ')) {
                $branch->setIsActive(false);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $byName = [];
        foreach (PresentationDemoCatalog::categories() as $group) {
            $parent = $this->entityManager->getRepository(Category::class)->findOneBy(['name' => $group['name']]);
            if (!$parent instanceof Category) {
                $parent = new Category()->setName($group['name']);
                $this->entityManager->persist($parent);
            }
            $byName[$group['name']] = $parent;

            foreach ($group['children'] as $childName) {
                $child = $this->entityManager->getRepository(Category::class)->findOneBy(['name' => $childName]);
                if (!$child instanceof Category) {
                    $child = new Category()
                        ->setName($childName)
                        ->setParent($parent);
                    $this->entityManager->persist($child);
                }
                $byName[$childName] = $child;
            }
        }

        $this->entityManager->flush();

        return $byName;
    }

    /**
     * @param array<string, Category> $categories
     *
     * @return list<Product>
     */
    private function seedProducts(array $categories, UnitOfMeasure $unit, Warehouse $warehouse): array
    {
        $products = [];
        foreach (PresentationDemoCatalog::products() as $row) {
            $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $row['code']]);
            if (!$product instanceof Product) {
                $category = $categories[$row['category']] ?? null;
                if (!$category instanceof Category) {
                    throw new RuntimeException(sprintf(
                        'Missing category "%s" for presentation products.',
                        $row['category'],
                    ));
                }
                $product = new Product()
                    ->setCode($row['code'])
                    ->setName($row['name'])
                    ->setType(1)
                    ->setCategory($category)
                    ->setUnitOfMeasure($unit)
                    ->setPurchasePrice($row['purchasePrice'])
                    ->setSalePrice($row['salePrice'])
                    ->setMinStock($row['minStock']);
                $this->entityManager->persist($product);

                $stock = new ProductStock()
                    ->setProduct($product)
                    ->setWarehouse($warehouse)
                    ->setStock((float) $row['stock'])
                    ->setMinStock((float) $row['minStock']);
                $this->entityManager->persist($stock);
            }
            $products[] = $product;
        }

        $this->entityManager->flush();

        foreach ($products as $index => $product) {
            $row = PresentationDemoCatalog::products()[$index];
            $this->connection->executeStatement(
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
                    'qty_on_hand' => number_format((float) $row['stock'], 6, '.', ''),
                ],
            );
        }

        return $products;
    }

    /**
     * @return list<Customer>
     */
    private function seedCustomers(): array
    {
        $customers = [];
        foreach (PresentationDemoCatalog::customers() as $row) {
            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['ruc' => $row['ruc']]);
            if (!$customer instanceof Customer) {
                $customer = new Customer()
                    ->setRuc($row['ruc'])
                    ->setBusinessName($row['name'])
                    ->setFiscalAddress($row['address'])
                    ->setPhone($row['phone'])
                    ->setEmail($row['email']);
                $this->entityManager->persist($customer);
            }
            $customers[] = $customer;
        }

        $this->entityManager->flush();

        return $customers;
    }

    private function seedHostingSubscription(
        Branch $branch,
        Warehouse $warehouse,
        Category $category,
        UnitOfMeasure $unit,
        Customer $customer,
    ): int {
        $productRepository = $this->entityManager->getRepository(Product::class);
        $product = $productRepository->findOneBy(['code' => 'HOST-WEB']);
        if (!$product instanceof Product) {
            $product = new Product()
                ->setCode('HOST-WEB')
                ->setName('Web Hosting')
                ->setType(2)
                ->setCategory($category)
                ->setUnitOfMeasure($unit)
                ->setProductKind(ProductKind::DIGITAL)
                ->setInventoryBehavior(InventoryBehavior::NOT_TRACKED)
                ->setPurchasePrice('0.00')
                ->setSalePrice('480.00')
                ->setMinStock('0.000');
            $this->entityManager->persist($product);
        }

        $planRepository = $this->entityManager->getRepository(SubscriptionPlan::class);
        $plan = $planRepository->findOneBy(['name' => 'Hosting anual']);
        if (!$plan instanceof SubscriptionPlan) {
            $plan = new SubscriptionPlan()
                ->setName('Hosting anual')
                ->setProduct($product)
                ->setBillingCycle(BillingCycle::ANNUAL)
                ->setAmount('480.00')
                ->setPaymentTermDays(7)
                ->setBranch($branch)
                ->setWarehouse($warehouse);
            $this->entityManager->persist($plan);
        }

        $subscriptionRepository = $this->entityManager->getRepository(CustomerSubscription::class);
        $subscription = $subscriptionRepository->findOneBy(['serviceReference' => 'andinaretail.pe']);
        if (!$subscription instanceof CustomerSubscription) {
            $periodStart = new DateTimeImmutable(
                'first day of this month',
                new DateTimeZone(PresentationDemoCatalog::TIMEZONE),
            );
            $periodEnd = $periodStart->modify('+1 year -1 day');
            $subscription = new CustomerSubscription()
                ->setCustomer($customer)
                ->setPlan($plan)
                ->setStatus('active')
                ->setStartDate($periodStart)
                ->setCurrentPeriodStart($periodStart)
                ->setCurrentPeriodEnd($periodEnd)
                ->setNextBillingDate($periodStart->modify('+1 year'))
                ->setEndDate($periodEnd)
                ->setAutoRenew(false)
                ->setServiceReference('andinaretail.pe')
                ->setCustomDescription('Hosting WordPress administrado por 12 meses')
                ->setAgreedAmount('480.00');
            $this->entityManager->persist($subscription);
        }

        if ($subscription->getLines()->isEmpty()) {
            $subscription->addLine(
                new CustomerSubscriptionLine()
                    ->setProduct($product)
                    ->setDescription('Hosting WordPress administrado')
                    ->setQuantity('1.000')
                    ->setUnitPrice('480.0000')
                    ->setSortOrder(1),
            );
        }

        $this->entityManager->flush();

        return 1;
    }

    /**
     * @return list<Supplier>
     */
    private function seedSuppliers(): array
    {
        $suppliers = [];
        foreach (PresentationDemoCatalog::suppliers() as $row) {
            $supplier = $this->entityManager->getRepository(Supplier::class)->findOneBy(['ruc' => $row['ruc']]);
            if (!$supplier instanceof Supplier) {
                $supplier = new Supplier()
                    ->setRuc($row['ruc'])
                    ->setBusinessName($row['name'])
                    ->setFiscalAddress($row['address'])
                    ->setPhone($row['phone']);
                $this->entityManager->persist($supplier);
            }
            $suppliers[] = $supplier;
        }

        $this->entityManager->flush();

        return $suppliers;
    }

    /**
     * @return array<string, PaymentMethod>
     */
    private function indexPaymentMethods(): array
    {
        $indexed = [];
        foreach ($this->entityManager->getRepository(PaymentMethod::class)->findAll() as $method) {
            $indexed[$method->getName()] = $method;
        }

        return $indexed;
    }

    /**
     * @param list<Customer> $customers
     * @param list<Product> $products
     * @param array<string, PaymentMethod> $paymentMethods
     *
     * @return list<SaleRecord>
     */
    private function seedSales(
        User $demoUser,
        Branch $branch,
        Warehouse $warehouse,
        array $customers,
        array $products,
        array $paymentMethods,
    ): array {
        $igv = (string) $this->tenantSettingsService->getIgv();
        $rotation = PresentationDemoCatalog::paymentMethodRotation();
        $fallbackMethod = $paymentMethods['Efectivo'] ?? reset($paymentMethods);
        if (!$fallbackMethod instanceof PaymentMethod) {
            throw new RuntimeException('No payment methods found. Run initial setup first.');
        }

        $userId = (int) $demoUser->getId();
        $facturaNumber = 1001;
        $boletaNumber = 2001;
        $sales = [];

        foreach (PresentationDemoCatalog::saleMoments() as $index => $moment) {
            $customer = $customers[$index % count($customers)];
            $methodName = $rotation[$index % count($rotation)];
            $method = $paymentMethods[$methodName] ?? $fallbackMethod;
            $credit = $methodName === 'Credito';
            $isCompany = str_starts_with($customer->getRuc(), '20');
            $series = $isCompany ? 'F001' : 'B001';
            $number = sprintf('%08d', $isCompany ? $facturaNumber++ : $boletaNumber++);
            $issuedAt = DateTime::createFromImmutable($moment);
            $issuedDate = DateTimeImmutable::createFromInterface($moment)->setTime(0, 0);

            $lineCount = 1 + ($index % 3);
            $lineInputs = [];
            $sale = new SaleRecord()
                ->setAlmacen($warehouse)
                ->setWarehouse($warehouse)
                ->setBranch($branch)
                ->setCliente($customer)
                ->setFecha($issuedAt)
                ->setIssuedDate($issuedDate)
                ->setFormaPago($credit ? 2 : PaymentMethod::CASH)
                ->setTipoPago($method)
                ->setSerie($series)
                ->setNumero($number)
                ->setDescuento('0.00')
                ->setHasInvoice(true)
                ->setInvoice($series . '-' . $number)
                ->setCajaId(1)
                ->setEstado(1)
                ->setStatus('COMPLETED')
                ->setDateCreated($moment)
                ->setCreatedBy($userId)
                ->setSellerUserId($userId)
                ->setCreatedAt($issuedAt)
                ->setLastModifiedAt($issuedAt)
                ->setSyncedAt($issuedAt)
                ->setIgv($igv)
                ->setRecargoConsumo(0)
                ->setTipoOperacion('0101')
                ->setCurrency('PEN');

            for ($line = 0; $line < $lineCount; ++$line) {
                $product = $products[($index + $line) % count($products)];
                $qty = (string) (1 + (($index + $line) % 3));
                $unitPrice = $product->getSalePrice() ?? '0.00';
                $lineInputs[] = new InvoiceLineInput(quantity: $qty, unitPrice: (string) $unitPrice);

                $item = new SaleItemRecord()
                    ->setProduct($product)
                    ->setQty(number_format((float) $qty, 3, '.', ''))
                    ->setUnitPrice(number_format((float) $unitPrice, 4, '.', ''))
                    ->setDiscountAmount('0.00')
                    ->setLineNo($line + 1)
                    ->setNameSnapshot($product->getName())
                    ->setSkuSnapshot($product->getCode())
                    ->setUomSnapshot($product->getUnitOfMeasure()?->getCode())
                    ->setUnitCostSnapshot($product->getPurchasePrice())
                    ->setLastModifiedAt($issuedAt)
                    ->setSyncedAt($issuedAt);
                $this->entityManager->persist($item);
                $sale->addSaleItemRecord($item);
            }

            $amounts = new InvoiceCalculator($lineInputs, '0', $igv, '0')->calculate();
            $sale
                ->setSubtotal($amounts->getSubtotal())
                ->setTaxTotal($amounts->getTax())
                ->setTotal($amounts->getTotal())
                ->setPaidTotal($credit ? '0.00' : $amounts->getTotal())
                ->setBalanceDue($credit ? $amounts->getTotal() : '0.00');

            if ($credit) {
                $sale->setDueDate($issuedDate->modify('+15 days'));
            }

            $lineIndex = 0;
            foreach ($sale->getSaleItemRecords() as $item) {
                $lineAmount = $amounts->getLines()[$lineIndex] ?? null;
                if ($lineAmount !== null) {
                    $item
                        ->setGrossAmount($lineAmount->totalAmount)
                        ->setTaxAmount($lineAmount->taxAmount)
                        ->setNetAmount($lineAmount->taxableAmount);
                }
                ++$lineIndex;
            }

            $taxDocument = new TaxDocument()
                ->setSaleRecord($sale)
                ->setCustomer($customer)
                ->setBranch($branch)
                ->setDocumentType($isCompany ? '01' : '03')
                ->setIssueDate($moment)
                ->setSeries($series)
                ->setNumber($number)
                ->setCurrency('PEN')
                ->setPaymentMethod($sale->getFormaPago())
                ->setSubtotal($amounts->getSubtotal())
                ->setTaxableAmount($amounts->getTaxedAmount())
                ->setIgvAmount($amounts->getTax())
                ->setRcAmount($amounts->getChargesAmount())
                ->setDiscountPercent('0')
                ->setDiscountAmount($amounts->getDiscountAmount())
                ->setOperationType('0101')
                ->setTotal($amounts->getTotal())
                ->setSunatStatus('0')
                ->setCreatedAt($issuedAt)
                ->setUpdatedAt($issuedAt);
            $this->entityManager->persist($taxDocument);
            $this->entityManager->persist($sale);
            $sales[] = $sale;
        }

        $this->entityManager->flush();

        foreach ($sales as $sale) {
            if ((float) $sale->getBalanceDue() > 0) {
                continue;
            }

            $method = $sale->getTipoPago();
            if (!$method instanceof PaymentMethod) {
                continue;
            }

            $paidAt = $sale->getIssuedAt() ?? new DateTime();
            $this->connection->executeStatement('INSERT INTO sale_payments (sale_id, payment_method_id, amount, currency, paid_at, created_at, sync_status, version_no, last_modified_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)', [
                (string) $sale->getId(),
                $method->getId(),
                $sale->getGrandTotal(),
                'PEN',
                $paidAt->format('Y-m-d H:i:s'),
                $paidAt->format('Y-m-d H:i:s'),
                'SYNCED',
                $paidAt->format('Y-m-d H:i:s'),
            ]);
        }

        return $sales;
    }

    /**
     * @param list<Supplier> $suppliers
     * @param list<Product> $products
     *
     * @return list<Purchase>
     */
    private function seedPurchases(
        User $demoUser,
        Branch $branch,
        Warehouse $warehouse,
        array $suppliers,
        array $products,
    ): array {
        $expenseRepo = $this->entityManager->getRepository(ExpenseCategory::class);
        $category = $expenseRepo->findOneBy(['code' => 'other_operating_expense']) ?? $expenseRepo->findOneBy([]);
        if (!$category instanceof ExpenseCategory) {
            throw new RuntimeException('No expense category found. Run initial setup first.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone(PresentationDemoCatalog::TIMEZONE));
        $purchases = [];
        $igvRate = 0.18;

        foreach ([3, 10, 18, 27, 40, 55] as $offset => $daysAgo) {
            $issued = $now->modify(sprintf('-%d days', $daysAgo))->setTime(10, 30);
            $issuedMutable = DateTime::createFromImmutable($issued);
            $supplier = $suppliers[$offset % count($suppliers)];
            $product = $products[$offset % count($products)];
            $qty = 6 + ($offset * 2);
            $unitCost = (float) ($product->getPurchasePrice() ?? '10.00');
            $gross = round($unitCost * $qty, 2);
            $tax = round($gross * $igvRate, 2);
            $total = round($gross + $tax, 2);

            $purchase = new Purchase()
                ->setBranch($branch)
                ->setWarehouse($warehouse)
                ->setSupplier($supplier)
                ->setBuyerUser($demoUser)
                ->setStatus('POSTED')
                ->setCurrency('PEN')
                ->setSubtotal(number_format($gross, 2, '.', ''))
                ->setTaxTotal(number_format($tax, 2, '.', ''))
                ->setGrandTotal(number_format($total, 2, '.', ''))
                ->setSupplierDocType('01')
                ->setSupplierDocSeries('F001')
                ->setSupplierDocNumber(sprintf('%08d', 4500 + $offset))
                ->setIssuedAt($issuedMutable)
                ->setCreatedAt($issuedMutable)
                ->setNote('Recepción de mercadería para demostración');

            $item = new PurchaseItem()
                ->setProduct($product)
                ->setLineNo(1)
                ->setNameSnapshot($product->getName())
                ->setSkuSnapshot($product->getCode())
                ->setUomSnapshot($product->getUnitOfMeasure()?->getCode())
                ->setQty((string) $qty)
                ->setUnitCost(number_format($unitCost, 4, '.', ''))
                ->setGrossAmount(number_format($gross, 2, '.', ''))
                ->setTaxAmount(number_format($tax, 2, '.', ''))
                ->setTotalAmount(number_format($total, 2, '.', ''));
            $purchase->addItem($item);
            $this->entityManager->persist($purchase);

            $paid = ($offset % 3) === 0;
            $bill = new SupplierBill()
                ->setSupplier($supplier)
                ->setBranch($branch)
                ->setPurchase($purchase)
                ->setCategory($category)
                ->setDocumentType('01')
                ->setSeries('F001')
                ->setNumber(sprintf('%08d', 4500 + $offset))
                ->setIssueDate($issued)
                ->setDueDate($issued->modify('+20 days'))
                ->setAmount(number_format($total, 2, '.', ''))
                ->setPaidTotal($paid ? number_format($total, 2, '.', '') : '0.00')
                ->setBalanceDue($paid ? '0.00' : number_format($total, 2, '.', ''))
                ->setStatus($paid ? SupplierBill::PAID : SupplierBill::PENDING)
                ->setNote($paid ? 'Pagada en tesorería' : 'Por pagar al proveedor');
            $this->entityManager->persist($bill);
            $purchases[] = $purchase;
        }

        $this->entityManager->flush();

        return $purchases;
    }

    /**
     * @param list<Supplier> $suppliers
     * @param list<Product> $products
     */
    private function seedPurchaseOrders(Branch $branch, Warehouse $warehouse, array $suppliers, array $products): void
    {
        $product = $products[0];
        $qty = '10.000';
        $unitCost = (float) ($product->getPurchasePrice() ?? '10.00');
        $gross = round($unitCost * 10, 2);
        $tax = round($gross * 0.18, 2);
        $total = round($gross + $tax, 2);

        $order = new PurchaseOrder()
            ->setBranch($branch)
            ->setWarehouse($warehouse)
            ->setSupplier($suppliers[0])
            ->setStatus(PurchaseOrder::STATUS_SENT)
            ->setCurrency('PEN')
            ->setSubtotal(number_format($gross, 2, '.', ''))
            ->setTaxTotal(number_format($tax, 2, '.', ''))
            ->setGrandTotal(number_format($total, 2, '.', ''))
            ->setExpectedDate(new DateTimeImmutable('+5 days'))
            ->setNote('Pedido abierto de reposición');

        $item = new PurchaseOrderItem()
            ->setProduct($product)
            ->setLineNo(1)
            ->setNameSnapshot($product->getName())
            ->setSkuSnapshot($product->getCode())
            ->setUomSnapshot($product->getUnitOfMeasure()?->getCode())
            ->setOrderedQty($qty)
            ->setExpectedUnitCost(number_format($unitCost, 4, '.', ''))
            ->setGrossAmount(number_format($gross, 2, '.', ''))
            ->setTaxAmount(number_format($tax, 2, '.', ''))
            ->setTotalAmount(number_format($total, 2, '.', ''));
        $order->addItem($item);
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    /**
     * @param list<Customer> $customers
     * @param list<Product> $products
     */
    private function seedQuotes(
        User $demoUser,
        Branch $branch,
        Warehouse $warehouse,
        array $customers,
        array $products,
    ): void {
        $igv = (string) $this->tenantSettingsService->getIgv();
        $now = new DateTimeImmutable('now', new DateTimeZone(PresentationDemoCatalog::TIMEZONE));
        $statuses = [SaleQuote::STATUS_DRAFT, SaleQuote::STATUS_SENT, SaleQuote::STATUS_ACCEPTED];

        foreach ($statuses as $index => $status) {
            $product = $products[$index % count($products)];
            $qty = '2.000';
            $unitPrice = (string) ($product->getSalePrice() ?? '10.00');
            $amounts = new InvoiceCalculator(
                [new InvoiceLineInput(quantity: $qty, unitPrice: $unitPrice)],
                '0',
                $igv,
                '0',
            )->calculate();

            $quote = new SaleQuote()
                ->setBranch($branch)
                ->setWarehouse($warehouse)
                ->setCustomer($customers[$index % count($customers)])
                ->setStatus($status)
                ->setCurrency('PEN')
                ->setSubtotal($amounts->getSubtotal())
                ->setTaxTotal($amounts->getTax())
                ->setGrandTotal($amounts->getTotal())
                ->setIssuedAt($now->modify(sprintf('-%d days', $index + 1)))
                ->setValidUntil($now->modify('+10 days'))
                ->setNote('Cotización de demostración');
            $quote->setSellerUserId($demoUser->getId());

            $item = new SaleQuoteItem()
                ->setProduct($product)
                ->setLineNo(1)
                ->setQty($qty)
                ->setUnitPrice(number_format((float) $unitPrice, 4, '.', ''))
                ->setGrossAmount($amounts->getSubtotal())
                ->setTaxAmount($amounts->getTax())
                ->setNetAmount($amounts->getTotal())
                ->setNameSnapshot($product->getName())
                ->setSkuSnapshot($product->getCode())
                ->setUomSnapshot($product->getUnitOfMeasure()?->getCode());
            $quote->addItem($item);
            $this->entityManager->persist($quote);
        }

        $this->entityManager->flush();
    }

    private function seedEmployeesAndPayroll(Company $company): void
    {
        foreach (PresentationDemoCatalog::afpRates() as $row) {
            $existing = $this->entityManager
                ->getRepository(AfpRate::class)
                ->findOneBy([
                    'afpProvider' => $row['provider'],
                ]);
            if ($existing instanceof AfpRate) {
                continue;
            }

            $rate = new AfpRate()
                ->setAfpProvider($row['provider'])
                ->setMandatoryContributionRate($row['mandatory'])
                ->setInsurancePremiumRate($row['insurance'])
                ->setCommissionRate($row['commission'])
                ->setEffectiveFrom(new DateTimeImmutable('2024-01-01'));
            $this->entityManager->persist($rate);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone(PresentationDemoCatalog::TIMEZONE));
        $employees = [];
        foreach (PresentationDemoCatalog::employees() as $row) {
            $employee = $this->entityManager->getRepository(Employee::class)->findOneBy(['dni' => $row['dni']]);
            if (!$employee instanceof Employee) {
                $employee = new Employee()
                    ->setCompany($company)
                    ->setDni($row['dni'])
                    ->setFirstName($row['firstName'])
                    ->setLastName($row['lastName'])
                    ->setHireDate($now->modify(sprintf('-%d months', $row['monthsAgoHired'])))
                    ->setBaseSalary($row['salary'])
                    ->setPensionScheme($row['pension'] === 'AFP' ? Employee::PENSION_AFP : Employee::PENSION_ONP)
                    ->setAfpProvider($row['afp'])
                    ->setStatus(Employee::ACTIVE);
                $this->entityManager->persist($employee);
            }
            $employees[] = $employee;
        }

        $this->entityManager->flush();

        $lastMonth = $now->modify('first day of last month');
        $this->createPayrollRun($company, $employees, $lastMonth, PayrollRun::PAID);
        $this->createPayrollRun($company, $employees, $now->modify('first day of this month'), PayrollRun::CALCULATED);
    }

    /**
     * @param list<Employee> $employees
     */
    private function createPayrollRun(
        Company $company,
        array $employees,
        DateTimeImmutable $period,
        string $status,
    ): void {
        $existing = $this->entityManager
            ->getRepository(PayrollRun::class)
            ->findOneBy([
                'company' => $company,
                'period' => $period,
                'type' => PayrollRun::REGULAR,
            ]);
        if ($existing instanceof PayrollRun) {
            return;
        }

        $run = new PayrollRun()
            ->setCompany($company)
            ->setPeriod($period)
            ->setType(PayrollRun::REGULAR)
            ->setStatus($status)
            ->setCalculatedAt(new DateTimeImmutable());
        if ($status === PayrollRun::PAID) {
            $run->setApprovedAt(new DateTimeImmutable())->setPaidAt(new DateTimeImmutable());
        }
        $this->entityManager->persist($run);

        foreach ($employees as $employee) {
            $entry = $this->payrollCalculationService->calculateRegular($employee, $period);
            $entry->setPayrollRun($run);
            $this->entityManager->persist($entry);
        }

        $this->entityManager->flush();
    }

    private function seedTreasuryMasters(): void
    {
        foreach (PresentationDemoCatalog::bankAccounts() as $row) {
            $existing = $this->entityManager
                ->getRepository(BankAccount::class)
                ->findOneBy([
                    'accountNumber' => $row['accountNumber'],
                ]);
            if ($existing instanceof BankAccount) {
                continue;
            }

            $account = new BankAccount()
                ->setBank($row['bank'])
                ->setAlias($row['alias'])
                ->setAccountNumber($row['accountNumber'])
                ->setCci($row['cci'])
                ->setCurrency('PEN')
                ->setActive(true);
            $this->entityManager->persist($account);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone(PresentationDemoCatalog::TIMEZONE));
        foreach (PresentationDemoCatalog::fixedAssets() as $row) {
            $existing = $this->entityManager
                ->getRepository(FixedAsset::class)
                ->findOneBy([
                    'description' => $row['description'],
                ]);
            if ($existing instanceof FixedAsset) {
                continue;
            }

            $asset = new FixedAsset()
                ->setDescription($row['description'])
                ->setPurchaseDate($now->modify(sprintf('-%d months', $row['monthsAgoPurchased'])))
                ->setCost($row['cost'])
                ->setUsefulLifeMonths($row['months'])
                ->setStatus(FixedAsset::ACTIVE);
            $this->entityManager->persist($asset);
        }

        $this->entityManager->flush();
    }

    /**
     * @param list<SaleRecord> $sales
     * @param list<Purchase> $purchases
     */
    private function postAccounting(array $sales, array $purchases): void
    {
        foreach ($sales as $sale) {
            $issued = $sale->getIssuedAt() ?? new DateTimeImmutable();
            $cogsTotal = 0.0;
            foreach ($sale->getSaleItemRecords() as $item) {
                $cogsTotal += (float) $item->getQty() * (float) ($item->getUnitCostSnapshot() ?? '0');
            }
            $cogs = number_format($cogsTotal, 2, '.', '');

            $this->postingService->post(new EconomicEvent(
                source: EconomicEvent::SOURCE_SALE,
                sourceId: (string) $sale->getId(),
                kind: (float) $sale->getBalanceDue() > 0 ? EconomicEvent::SALE_CREDIT : EconomicEvent::SALE_CASH,
                occurredOn: DateTimeImmutable::createFromInterface($issued)->format('Y-m-d'),
                description: 'Venta ' . trim($sale->getSerie() . '-' . $sale->getNumero(), '-'),
                total: (string) $sale->getGrandTotal(),
                tax: (string) $sale->getTaxTotal(),
                cogs: $cogs,
            ));
        }

        foreach ($purchases as $purchase) {
            $issued = $purchase->getIssuedAt() ?? new DateTimeImmutable();
            $this->postingService->post(new EconomicEvent(
                source: EconomicEvent::SOURCE_PURCHASE,
                sourceId: (string) $purchase->getId(),
                kind: EconomicEvent::PURCHASE,
                occurredOn: DateTimeImmutable::createFromInterface($issued)->format('Y-m-d'),
                description: 'Compra ' . (string) ($purchase->getSupplierDocNumber() ?? $purchase->getId()),
                total: $purchase->getGrandTotal(),
                tax: $purchase->getTaxTotal(),
            ));
        }
    }

    private function finalizePresentation(User $user, Branch $branch): void
    {
        $this->connection->executeStatement('DELETE FROM branch_daily_summary');
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO branch_daily_summary
                (summary_date, branch_id, sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total)
            SELECT
                DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id, COUNT(*),
                COALESCE(SUM(grand_total), 0), COALESCE(SUM(discount_total), 0),
                COALESCE(SUM(tax_total), 0), COALESCE(SUM(grand_total - tax_total), 0), 0
            FROM sales
            WHERE status != 'VOIDED'
            GROUP BY DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id
            SQL);

        $this->dashboardRepository->invalidate((string) $branch->getId());

        $cashRegister = $this->entityManager->getRepository(CashRegister::class)->findOneBy(['branch' => $branch]);
        if ($cashRegister === null) {
            return;
        }

        $open = $this->entityManager
            ->getRepository(CashSession::class)
            ->findOneBy([
                'cashRegister' => $cashRegister,
                'status' => 'OPEN',
            ]);
        if ($open instanceof CashSession) {
            return;
        }

        $now = new DateTimeImmutable();
        $userId = $user->getId();
        if ($userId === null) {
            throw new RuntimeException('No se pudo resolver el usuario de presentación para abrir caja.');
        }

        $openedBy = $this->entityManager->find(User::class, $userId);
        if (!$openedBy instanceof User) {
            throw new RuntimeException('No se pudo resolver el usuario de presentación para abrir caja.');
        }

        $session = new CashSession()
            ->setCashRegister($cashRegister)
            ->setBranch($branch)
            ->setOpenedBy($openedBy)
            ->setStatus('OPEN')
            ->setOpeningAmount('200.00')
            ->setOpenedAt($now)
            ->setCreatedAt($now);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $this->connection->executeStatement(
            'INSERT INTO cash_movements (cash_session_id, movement_type, amount, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$session->getId(), 'OPENING', '200.00', 'Saldo inicial de demostración', $userId],
        );
    }
}
