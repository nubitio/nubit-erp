<?php

declare(strict_types=1);

namespace App\Setup\Service;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\UnitOfMeasure;
use App\Billing\Service\InvoiceCalculator;
use App\Billing\Service\InvoiceLineInput;
use App\Contacts\Entity\Customer;
use App\Billing\Entity\TaxDocument;
use App\Inventory\Entity\LogisticOperation;
use App\Catalog\Entity\Product;
use App\Inventory\Entity\ProductStock;
use App\Sales\Entity\SaleRecord;
use App\Sales\Entity\SaleItemRecord;
use App\Contacts\Entity\Supplier;
use App\Inventory\Entity\Warehouse;
use App\Catalog\Repository\CategoryRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Inventory\Repository\LogisticOperationRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\ProductRepository;
use App\Contacts\Repository\SupplierRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Inventory\Repository\WarehouseRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TestDataSeeder
{
    private const array PRODUCT_CATEGORIES = [
        'Electrónica' => ['Computadoras', 'Celulares', 'Tablets', 'Accesorios'],
        'Alimentos' => ['Bebidas', 'Snacks', 'Enlatados', 'Congelados', 'Panadería'],
        'Ropa' => ['Camisas', 'Pantalones', 'Zapatos', 'Accesorios de Moda'],
        'Hogar' => ['Muebles', 'Decoración', 'Electrodomésticos', 'Cocina'],
        'Oficina' => ['Papelería', 'Equipos', 'Mobiliario', 'Tecnología'],
        'Deportes' => ['Ropa Deportiva', 'Equipamiento', 'Suplementos'],
        'Belleza' => ['Cosméticos', 'Cuidado Personal', 'Perfumes'],
    ];

    private const array UNIT_CODES = [
        ['code' => 'KGM', 'name' => 'Kilogramo', 'type' => 2],
        ['code' => 'MTR', 'name' => 'Metro', 'type' => 3],
        ['code' => 'LTR', 'name' => 'Litro', 'type' => 4],
        ['code' => 'SET', 'name' => 'Conjunto', 'type' => 1],
        ['code' => 'DZN', 'name' => 'Docena', 'type' => 1],
        ['code' => 'GRM', 'name' => 'Gramo', 'type' => 2],
        ['code' => 'MLT', 'name' => 'Mililitro', 'type' => 4],
        ['code' => 'BX', 'name' => 'Caja', 'type' => 1],
        ['code' => 'PK', 'name' => 'Paquete', 'type' => 1],
    ];

    private const array COMPANY_TYPES = ['SAC', 'SRL', 'EIRL', 'SA', 'SAS'];

    private const array BUSINESS_SECTORS = [
        'Distribuidora',
        'Comercializadora',
        'Importadora',
        'Exportadora',
        'Servicios',
        'Consultora',
        'Constructora',
        'Restaurant',
        'Tienda',
        'Mayorista',
        'Minorista',
        'Manufactura',
    ];

    private const array PERUVIAN_CITIES = [
        'Lima',
        'Arequipa',
        'Cusco',
        'Trujillo',
        'Chiclayo',
        'Piura',
        'Iquitos',
        'Huancayo',
        'Tacna',
        'Puno',
    ];

    private const array PRODUCT_NAMES = [
        'Laptop',
        'Mouse Inalámbrico',
        'Teclado Mecánico',
        'Monitor LED',
        'Webcam HD',
        'Audífonos Bluetooth',
        'Smartphone',
        'Tablet',
        'Cargador USB-C',
        'Cable HDMI',
        'Gaseosa 500ml',
        'Agua Mineral',
        'Jugo Natural',
        'Café Premium',
        'Té Verde',
        'Galletas',
        'Chocolate',
        'Caramelos',
        'Papas Fritas',
        'Nachos',
        'Arroz',
        'Fideos',
        'Aceite Vegetal',
        'Azúcar',
        'Sal',
        'Leche',
        'Yogurt',
        'Queso',
        'Mantequilla',
        'Huevos',
        'Pan Integral',
        'Pan Francés',
        'Croissant',
        'Donas',
        'Torta',
        'Camisa Casual',
        'Polo Deportivo',
        'Pantalón Jean',
        'Short',
        'Vestido',
        'Zapatillas Running',
        'Sandalias',
        'Botas',
        'Medias',
        'Gorra',
        'Sillón',
        'Mesa de Centro',
        'Estante',
        'Lámpara LED',
        'Cortinas',
        'Olla',
        'Sartén',
        'Licuadora',
        'Microondas',
        'Refrigeradora',
        'Cuaderno A4',
        'Lapicero Azul',
        'Resaltador',
        'Corrector',
        'Grapadora',
        'Archivador',
        'Folder Manila',
        'Clips',
        'Tijeras',
        'Regla',
        'Pesas',
        'Colchoneta Yoga',
        'Balón Fútbol',
        'Raqueta Tennis',
        'Bicicleta',
        'Proteína Whey',
        'Creatina',
        'BCAA',
        'Vitaminas',
        'Omega 3',
        'Shampoo',
        'Acondicionador',
        'Jabón Líquido',
        'Crema Facial',
        'Perfume',
        'Lápiz Labial',
        'Máscara Pestañas',
        'Base Maquillaje',
        'Sombra Ojos',
        'Esmalte Uñas',
    ];

    private const array PERSON_NAMES = [
        'Juan',
        'María',
        'Carlos',
        'Ana',
        'Luis',
        'Rosa',
        'Pedro',
        'Carmen',
        'Jorge',
        'Laura',
        'Miguel',
        'Isabel',
        'José',
        'Patricia',
        'Roberto',
    ];

    private const array LAST_NAMES = [
        'García',
        'Rodríguez',
        'Pérez',
        'López',
        'González',
        'Martínez',
        'Sánchez',
        'Torres',
        'Ramírez',
        'Flores',
        'Rivera',
        'Gómez',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private WarehouseRepository $warehouseRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
        private CategoryRepository $categoryRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private LogisticOperationRepository $logisticOperationRepository,
        private SupplierRepository $supplierRepository,
        private CustomerRepository $customerRepository,
        private ProductRepository $productRepository,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function seed(
        int $customerCount = 300,
        int $productCount = 500,
        int $supplierCount = 20,
        int $saleCount = 200,
        int $batchSize = 50
    ): array {
        $stats = [
            'categories_created' => 0,
            'units_created' => 0,
            'suppliers_created' => 0,
            'customers_created' => 0,
            'products_created' => 0,
            'sales_created' => 0,
            'invoices_created' => 0,
        ];

        $warehouse = $this->getFirstWarehouse();

        $stats['categories_created'] = $this->seedCategories();
        $stats['units_created'] = $this->seedUnitsOfMeasure();

        $categories = $this->getAllChildCategories();
        $units = $this->getAllUnits();
        $paymentMethods = $this->getPaymentMethods();
        $logisticOperation = $this->getSaleLogisticOperation();

        $stats['suppliers_created'] = $this->seedSuppliers($supplierCount, $batchSize);
        $stats['customers_created'] = $this->seedCustomers($customerCount, $batchSize);
        $stats['products_created'] = $this->seedProducts($productCount, $categories, $units, $warehouse, $batchSize);

        $suppliers = $this->supplierRepository->findAll();
        $customers = $this->customerRepository->findAll();
        $products = $this->productRepository->findAll();

        $stats['sales_created'] = $this->seedSales(
            $saleCount,
            $customers,
            $products,
            $suppliers,
            $warehouse,
            $paymentMethods,
            $logisticOperation,
            $batchSize
        );

        // Invoices are created with sales, so they're the same count
        $stats['invoices_created'] = $stats['sales_created'];

        return $stats;
    }

    private function getFirstWarehouse(): Warehouse
    {
        $warehouse = $this->warehouseRepository->findOneBy([]);
        if (!$warehouse instanceof Warehouse) {
            throw new \RuntimeException('No warehouse found. Run initial setup first.');
        }

        return $warehouse;
    }

    private function seedCategories(): int
    {
        $count = 0;
        foreach (self::PRODUCT_CATEGORIES as $parentName => $children) {
            $parent = $this->categoryRepository->findOneBy(['name' => $parentName]);
            if (!$parent instanceof Category) {
                $parent = new Category();
                $parent->setName($parentName);
                $this->entityManager->persist($parent);
                ++$count;
            }

            foreach ($children as $childName) {
                $child = $this->categoryRepository->findOneBy(['name' => $childName]);
                if (!$child instanceof Category) {
                    $child = new Category();
                    $child->setName($childName)->setParent($parent);
                    $this->entityManager->persist($child);
                    ++$count;
                }
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    private function seedUnitsOfMeasure(): int
    {
        $count = 0;
        foreach (self::UNIT_CODES as $unitData) {
            $unit = $this->unitOfMeasureRepository->findOneBy(['code' => $unitData['code']]);
            if (!$unit instanceof UnitOfMeasure) {
                $unit = new UnitOfMeasure();
                $unit
                    ->setName($unitData['name'])
                    ->setCode($unitData['code'])
                    ->setType($unitData['type'])
                    ->setRatio('1.00')
                    ->setReference(false);
                $this->entityManager->persist($unit);
                ++$count;
            }
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * @return array<int, Category>
     */
    private function getAllChildCategories(): array
    {
        return array_values(
            array_filter(
                $this->categoryRepository->findAll(),
                static fn (Category $cat) => $cat->getParent() !== null
            )
        );
    }

    /**
     * @return array<int, UnitOfMeasure>
     */
    private function getAllUnits(): array
    {
        return $this->unitOfMeasureRepository->findAll();
    }

    /**
     * @return array<int, mixed>
     */
    private function getPaymentMethods(): array
    {
        return $this->paymentMethodRepository->findAll();
    }

    private function getSaleLogisticOperation(): LogisticOperation
    {
        $operation = $this->logisticOperationRepository->findOneBy(['name' => 'Venta']);
        if (!$operation instanceof LogisticOperation) {
            throw new \RuntimeException('Venta operation not found. Run initial setup first.');
        }

        return $operation;
    }

    private function seedSuppliers(int $count, int $batchSize): int
    {
        $created = 0;
        for ($i = 1; $i <= $count; ++$i) {
            $ruc = '20' . str_pad((string)(400000000 + $i), 9, '0', STR_PAD_LEFT);
            $existing = $this->supplierRepository->findOneBy(['ruc' => $ruc]);

            if (!$existing instanceof Supplier) {
                $sector = self::BUSINESS_SECTORS[array_rand(self::BUSINESS_SECTORS)];
                $type = self::COMPANY_TYPES[array_rand(self::COMPANY_TYPES)];
                $city = self::PERUVIAN_CITIES[array_rand(self::PERUVIAN_CITIES)];

                $supplier = new Supplier();
                $supplier
                    ->setRuc($ruc)
                    ->setBusinessName($sector . ' ' . chr(64 + ($i % 26) + 1) . $i . ' ' . $type)
                    ->setFiscalAddress('Av. Industrial ' . (100 + $i) . ' ' . $city)
                    ->setPhone('+51 9' . str_pad((string)(10000000 + $i), 8, '0', STR_PAD_LEFT));

                $this->entityManager->persist($supplier);
                ++$created;

                if (0 === $created % $batchSize) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $created;
    }

    private function seedCustomers(int $count, int $batchSize): int
    {
        $created = 0;
        for ($i = 1; $i <= $count; ++$i) {
            $isCompany = $i % 3 !== 0;
            $ruc = $isCompany
                ? '20' . str_pad((string)(100000000 + $i), 9, '0', STR_PAD_LEFT)
                : '10' . str_pad((string)(100000 + $i), 6, '0', STR_PAD_LEFT);

            $existing = $this->customerRepository->findOneBy(['ruc' => $ruc]);

            if (!$existing instanceof Customer) {
                if ($isCompany) {
                    $sector = self::BUSINESS_SECTORS[array_rand(self::BUSINESS_SECTORS)];
                    $type = self::COMPANY_TYPES[array_rand(self::COMPANY_TYPES)];
                    $businessName = $sector . ' ' . chr(64 + ($i % 26) + 1) . $i . ' ' . $type;
                } else {
                    $firstName = self::PERSON_NAMES[array_rand(self::PERSON_NAMES)];
                    $lastName1 = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                    $lastName2 = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
                    $businessName = $firstName . ' ' . $lastName1 . ' ' . $lastName2;
                }

                $city = self::PERUVIAN_CITIES[array_rand(self::PERUVIAN_CITIES)];

                $customer = new Customer();
                $customer
                    ->setRuc($ruc)
                    ->setBusinessName($businessName)
                    ->setFiscalAddress('Jr. Comercio ' . (100 + $i) . ' ' . $city)
                    ->setPhone('+51 9' . str_pad((string)(20000000 + $i), 8, '0', STR_PAD_LEFT))
                    ->setEmail('cliente' . $i . '@test.com');

                $this->entityManager->persist($customer);
                ++$created;

                if (0 === $created % $batchSize) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->syncLegacyProductStockMirrorToStockItems();

        return $created;
    }

    private function syncLegacyProductStockMirrorToStockItems(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, version)
            SELECT CAST(ps.warehouse_id AS CHAR), CAST(ps.product_id AS CHAR), ps.stock, 0, NOW(), 1
            FROM product_stock ps
            ON DUPLICATE KEY UPDATE
                qty_on_hand = VALUES(qty_on_hand),
                qty_reserved = 0,
                updated_at = NOW(),
                version = version + 1
            SQL
        );
    }

    /**
     * @param array<int, Category> $categories
     * @param array<int, UnitOfMeasure> $units
     */
    private function seedProducts(
        int $count,
        array $categories,
        array $units,
        Warehouse $warehouse,
        int $batchSize
    ): int {
        $created = 0;
        $warehouseId = $warehouse->getId();

        // Get IDs to reload entities after clearing
        $categoryIds = array_map(fn ($c) => $c->getId(), $categories);
        $unitIds = array_map(fn ($u) => $u->getId(), $units);

        for ($i = 1; $i <= $count; ++$i) {
            $code = 'PROD-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
            $existing = $this->productRepository->findOneBy(['code' => $code]);

            if (!$existing instanceof Product) {
                // Reload entities from database to avoid detachment
                $category = $this->categoryRepository->find($categoryIds[array_rand($categoryIds)]);
                $unit = $this->unitOfMeasureRepository->find($unitIds[array_rand($unitIds)]);
                $warehouse = $this->warehouseRepository->find($warehouseId);

                $name = self::PRODUCT_NAMES[($i - 1) % count(self::PRODUCT_NAMES)] . ' #' . $i;
                $purchasePrice = number_format(random_int(10, 1000) / 10, 2, '.', '');
                $salePrice = number_format((float) $purchasePrice * (1 + (random_int(20, 80) / 100)), 2, '.', '');
                $stock = (string) random_int(0, 500);
                $minStock = (string) random_int(5, 20);

                $product = new Product();
                $product
                    ->setCode($code)
                    ->setName($name)
                    ->setType(1)
                    ->setCategory($category)
                    ->setUnitOfMeasure($unit)
                    ->setPurchasePrice($purchasePrice)
                    ->setSalePrice($salePrice)
                    ->setMinStock($minStock);

                $this->entityManager->persist($product);

                $productStock = new ProductStock();
                $productStock
                    ->setProduct($product)
                    ->setWarehouse($warehouse)
                    ->setStock((float) $stock)
                    ->setMinStock((float) $minStock);

                $this->entityManager->persist($productStock);
                ++$created;

                if (0 === $created % $batchSize) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $created;
    }

    /**
     * @param array<int, mixed> $customers
     * @param array<int, mixed> $products
     * @param array<int, mixed> $suppliers
     * @param array<int, mixed> $paymentMethods
     */
    private function seedSales(
        int $count,
        array $customers,
        array $products,
        array $suppliers,
        Warehouse $warehouse,
        array $paymentMethods,
        LogisticOperation $logisticOperation,
        int $batchSize
    ): int {
        $created = 0;
        $warehouseId = $warehouse->getId();
        $logisticOperationId = $logisticOperation->getId();

        // Get IDs for all entities to avoid detachment issues
        $customerIds = array_map(fn ($c) => $c->getId(), $customers);
        $supplierIds = array_map(fn ($s) => $s->getId(), $suppliers);
        $paymentMethodIds = array_map(fn ($p) => $p->getId(), $paymentMethods);
        $productIds = array_map(fn ($p) => $p->getId(), $products);

        for ($i = 1; $i <= $count; ++$i) {
            $glosa = 'Venta Test #' . $i;

            $daysAgo = random_int(1, 30);
            $saleDate = new \DateTimeImmutable("-$daysAgo days");

            // Reload entities from database to avoid detached entities
            $customer = $this->customerRepository->find($customerIds[array_rand($customerIds)]);
            $supplier = $this->supplierRepository->find($supplierIds[array_rand($supplierIds)]);
            $paymentMethod = $this->paymentMethodRepository->find($paymentMethodIds[array_rand($paymentMethodIds)]);
            $warehouse = $this->warehouseRepository->find($warehouseId);
            $logisticOperation = $this->logisticOperationRepository->find($logisticOperationId);

            if ($customer->getRuc()[0] === '1') {
                $series = 'B001';
            } else {
                $series = 'F001';
            }

            $sale = new SaleRecord();
            $sale->setAlmacen($warehouse);
            $sale->setCliente($customer);
            $sale->setTipoId(2);
            $sale->setFecha($saleDate);
            $sale->setProveedor($supplier);
            $sale->setFormaPago(random_int(1, 2));
            $sale->setTipoPago($paymentMethod);
            $sale->setSerie('NV01');
            $sale->setNumero(sprintf('%08d', 1000 + $i));
            $sale->setDescuento('0.00');
            $sale->setGlosa($glosa);
            $sale->setHasInvoice(true);
            $sale->setInvoice($series . '-' . $sale->getNumero());
            $sale->setCajaId(1);
            $sale->setEstado(1);
            $sale->setDateCreated($saleDate);
            $sale->setCreatedBy(1);
            $sale->setLastModifiedAt($saleDate);
            $sale->setSyncedAt($saleDate);
            $sale->setIgv('18');
            $sale->setRecargoConsumo(0);

            $subtotal = 0.0;
            $itemCount = random_int(1, 8);

            for ($j = 0; $j < $itemCount; ++$j) {
                $product = $this->productRepository->find($productIds[array_rand($productIds)]);
                $qty = random_int(1, 10);
                $unitPrice = (float) $product->getSalePrice();
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $saleItem = new SaleItemRecord();
                $saleItem->setProduct($product);
                $saleItem->setQty((string) $qty);
                $saleItem->setUnitPrice((string) $unitPrice);
                $saleItem->setDiscountAmount('0');
                $saleItem->setGrossAmount(number_format($lineTotal, 2, '.', ''));
                $saleItem->setNetAmount(number_format($lineTotal, 2, '.', ''));
                $saleItem->setTaxAmount('0.00');
                $saleItem->setLineNo($j + 1);
                $saleItem->setNameSnapshot($product->getName());
                $saleItem->setSkuSnapshot($product->getCode());
                $saleItem->setUomSnapshot($product->getUnitOfMeasure()->getCode());
                $saleItem->setLastModifiedAt($saleDate);
                $saleItem->setSyncedAt($saleDate);

                $this->entityManager->persist($saleItem);
                $sale->addSaleItemRecord($saleItem);
            }

            $total = $subtotal - ($subtotal * ($sale->getDescuento() / 100));
            $sale->setSubtotal(number_format($subtotal, 2, '.', ''));
            $sale->setTotal(number_format($total, 2, '.', ''));

            $this->entityManager->persist($sale);

            $lines = [];
            foreach ($sale->getSaleItemRecords() as $item) {
                $lines[] = new InvoiceLineInput(
                    quantity: $item->getQty(),
                    unitPrice: $item->getUnitPrice()
                );
            }

            $invoiceAmounts = new InvoiceCalculator(
                $lines,
                $sale->getDescuento() ?? '0',
                (string) ($sale->getIgv() ?? '0'),
                (string) ($sale->getRecargoConsumo() ?? '0'),
                $sale->getTotal() !== null ? (string) $sale->getTotal() : null
            )->calculate();

            // Create corresponding tax document
            $taxDocument = new TaxDocument();
            $taxDocument->setCustomer($customer);
            $taxDocument->setDocumentType(($customer->getRuc()[0] === '1') ? '03' : '01');
            $taxDocument->setIssueDate($saleDate);
            $taxDocument->setSeries($series);
            $taxDocument->setNumber($sale->getNumero());
            $taxDocument->setCurrency('PEN');
            $taxDocument->setPaymentMethod($sale->getFormaPago());
            $taxDocument->setSubtotal($invoiceAmounts->getSubtotal());
            $taxDocument->setTaxableAmount($invoiceAmounts->getTaxedAmount());
            $taxDocument->setIgvAmount($invoiceAmounts->getTax());
            $taxDocument->setRcAmount($invoiceAmounts->getChargesAmount());
            $taxDocument->setDiscountPercent($sale->getDescuento() ?? '0');
            $taxDocument->setDiscountAmount($invoiceAmounts->getDiscountAmount());
            $taxDocument->setOperationType($sale->getTipoOperacion());
            $taxDocument->setDetractionCode($sale->getDetraccionCodigo());
            $taxDocument->setDetractionPercent($sale->getDetraccionPorcentaje());
            $taxDocument->setDetractionAmount($sale->getDetraccionMonto());
            $taxDocument->setDetractionPaymentMethod($sale->getDetraccionMedioPago());
            $taxDocument->setDetractionValueRef($sale->getDetraccionValorReferencial());
            $taxDocument->setTotal($invoiceAmounts->getTotal());
            $taxDocument->setCreatedAt($saleDate);
            $taxDocument->setUpdatedAt($saleDate);

            $this->entityManager->persist($taxDocument);
            ++$created;

            if (0 === $created % $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        return $created;
    }
}
