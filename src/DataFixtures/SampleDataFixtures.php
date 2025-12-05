<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Branch;
use App\Entity\Category;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Entity\PaymentMethod;
use App\Entity\PriceList;
use App\Entity\Product;
use App\Entity\ProductStock;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Supplier;
use App\Entity\UnitOfMeasure;
use App\Entity\Warehouse;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds catalog data and a few movements (sales) for local testing.
 * The fixture is idempotent so it can be rerun with --append without blowing up unique constraints.
 */
final class SampleDataFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $company = $this->getOrCreate(
            $manager,
            Company::class,
            ['ruc' => '20600000001'],
            static function (): Company {
                return (new Company())
                    ->setRuc('20600000001')
                    ->setBusinessName('Efact Demo SAC')
                    ->setTradeName('Efact Demo')
                    ->setFiscalAddress('Av. Demo 123 Lima')
                    ->setPhone('012345678');
            }
        );

        $branch = $this->getOrCreate(
            $manager,
            Branch::class,
            ['name' => 'Local Central'],
            static function () use ($company): Branch {
                return (new Branch())
                    ->setName('Local Central')
                    ->setAddress('Av. Demo 123 Lima')
                    ->setPhone('012345678')
                    ->setCompany($company);
            }
        );

        $warehouse = $this->getOrCreate(
            $manager,
            Warehouse::class,
            ['name' => 'Almacén Principal'],
            static function () use ($branch): Warehouse {
                return (new Warehouse())
                    ->setName('Almacén Principal')
                    ->setBranch($branch);
            }
        );

        $unit = $this->getOrCreate(
            $manager,
            UnitOfMeasure::class,
            ['code' => 'NIU'],
            static function (): UnitOfMeasure {
                return (new UnitOfMeasure())
                    ->setName('Unidad')
                    ->setCode('NIU')
                    ->setType(1)
                    ->setRatio(1.0)
                    ->setReference(true);
            }
        );

        $categories = [];
        foreach (['Bebidas', 'Snacks', 'Tecnología'] as $categoryName) {
            $categories[$categoryName] = $this->getOrCreate(
                $manager,
                Category::class,
                ['name' => $categoryName],
                static function () use ($categoryName): Category {
                    return (new Category())->setName($categoryName);
                }
            );
        }

        $paymentMethod = $this->getOrCreate(
            $manager,
            PaymentMethod::class,
            ['name' => 'Efectivo'],
            static function (): PaymentMethod {
                return (new PaymentMethod())->setName('Efectivo');
            }
        );

        $priceList = $this->getOrCreate(
            $manager,
            PriceList::class,
            ['name' => 'Lista General'],
            static function (): PriceList {
                return (new PriceList())->setName('Lista General');
            }
        );

        $logisticOperation = $this->getOrCreate(
            $manager,
            LogisticOperation::class,
            ['name' => 'Venta'],
            static function (): LogisticOperation {
                return (new LogisticOperation())
                    ->setName('Venta')
                    ->setType(1);
            }
        );

        $suppliers = [
            $this->getOrCreate(
                $manager,
                Supplier::class,
                ['ruc' => '20567890123'],
                static function (): Supplier {
                    return (new Supplier())
                        ->setRuc('20567890123')
                        ->setBusinessName('Distribuidora Peruana SAC')
                        ->setFiscalAddress('Av. Industrial 456 Lima')
                        ->setPhone('+51 912345678');
                }
            ),
            $this->getOrCreate(
                $manager,
                Supplier::class,
                ['ruc' => '20456789012'],
                static function (): Supplier {
                    return (new Supplier())
                        ->setRuc('20456789012')
                        ->setBusinessName('Importaciones Andinas SRL')
                        ->setFiscalAddress('Jr. Comercio 789 Arequipa')
                        ->setPhone('+51 987654321');
                }
            ),
        ];

        $customers = [
            $this->getOrCreate(
                $manager,
                Customer::class,
                ['ruc' => '20123456789'],
                static function (): Customer {
                    return (new Customer())
                        ->setRuc('20123456789')
                        ->setBusinessName('Cliente Demostración SAC')
                        ->setEmail('cliente+demo1@example.com')
                        ->setFiscalAddress('Av. Siempre Viva 742');
                }
            ),
            $this->getOrCreate(
                $manager,
                Customer::class,
                ['ruc' => '10987654321'],
                static function (): Customer {
                    return (new Customer())
                        ->setRuc('10987654321')
                        ->setBusinessName('Consumidor Final')
                        ->setEmail('cliente+demo2@example.com')
                        ->setFiscalAddress('Calle Principal 123');
                }
            ),
        ];

        $products = [];
        $productDefinitions = [
            [
                'code' => 'P-001',
                'name' => 'Gaseosa 500ml',
                'category' => 'Bebidas',
                'purchase' => 2.00,
                'sale' => 3.50,
                'stock' => 120,
            ],
            [
                'code' => 'P-002',
                'name' => 'Snack Papas',
                'category' => 'Snacks',
                'purchase' => 1.00,
                'sale' => 1.80,
                'stock' => 150,
            ],
            [
                'code' => 'P-003',
                'name' => 'Mouse Inalámbrico',
                'category' => 'Tecnología',
                'purchase' => 35.00,
                'sale' => 49.90,
                'stock' => 40,
            ],
        ];

        foreach ($productDefinitions as $definition) {
            $product = $manager->getRepository(Product::class)->findOneBy(['name' => $definition['name']]);
            if (!$product instanceof Product) {
                $product = $manager->getRepository(Product::class)->findOneBy(['code' => $definition['code']]);
            }

            if (!$product instanceof Product) {
                $product = new Product();
            }

            $product
                ->setCode($definition['code'])
                ->setName($definition['name'])
                ->setType(1)
                ->setCategory($categories[$definition['category']])
                ->setUnitOfMeasure($unit)
                ->setPurchasePrice($definition['purchase'])
                ->setSalePrice($definition['sale'])
                ->setStock($definition['stock'])
                ->setMinStock(5);

            $manager->persist($product);
            $products[] = $product;

            $stock = $this->getOrCreate(
                $manager,
                ProductStock::class,
                [
                    'product' => $product,
                    'warehouse' => $warehouse,
                ],
                static function () {
                    return new ProductStock();
                }
            );

            $stock
                ->setProduct($product)
                ->setWarehouse($warehouse)
                ->setStock($definition['stock'])
                ->setMinStock(5);

            $manager->persist($stock);
        }

        // Seed a handful of sales with items.
        for ($i = 1; $i <= 3; $i++) {
            $existingSale = $manager->getRepository(Sale::class)->findOneBy(['glosa' => 'Venta de prueba #' . $i]);
            if ($existingSale instanceof Sale) {
                continue;
            }

            $saleDate = (new DateTimeImmutable())->sub(new DateInterval('PT' . (12 * $i) . 'H'));
            $customer = $customers[$i % count($customers)];
            $supplier = $suppliers[$i % count($suppliers)];
            $sale = new Sale();
            $sale->setOperacion($logisticOperation);
            $sale->setAlmacen($warehouse);
            $sale->setCliente($customer);
            $sale->setPriceList($priceList);
            $sale->setTipoId(1);
            $sale->setFecha($saleDate);
            $sale->setProveedor($supplier);
            $sale->setFormaPago(1);
            $sale->setTipoPago($paymentMethod);
            $sale->setTipoTarjeta(0);
            $sale->setSerie('F001');
            $sale->setNumero(sprintf('%08d', 200 + $i));
            $sale->setDescuento(0.0);
            $sale->setGlosa('Venta de prueba #' . $i);
            $sale->setOrdenId($i);
            $sale->setHasInvoice(true);
            $sale->setIsConsume(false);
            $sale->setCajaId(1);
            $sale->setEstado(1);
            $sale->setDateCreated($saleDate);
            $sale->setDateUpdated($saleDate);
            $sale->setCreatedBy(1);
            $sale->setUpdatedBy(1);
            $sale->setIgv(18);
            $sale->setRecargoConsumo(0);
            $sale->setInvoice('PENDING');

            $subtotal = 0.0;
            $items = [
                ['product' => $products[0], 'qty' => 2],
                ['product' => $products[1], 'qty' => 1],
            ];

            foreach ($items as $itemData) {
                $product = $itemData['product'];
                $qty = $itemData['qty'];
                $unitPrice = $product->getSalePrice() ?? 0.0;
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $saleItem = new SaleItem();
                $saleItem->setMovimiento($sale);
                $saleItem->setProducto($product);
                $saleItem->setCantidad($qty);
                $saleItem->setPrecioUnitario($unitPrice);
                $saleItem->setDescuento(0.0);
                $saleItem->setPrecioTotal($lineTotal);

                $manager->persist($saleItem);
                $sale->addItem($saleItem);
            }

            $total = round($subtotal * 1.18, 2);
            $sale
                ->setSubtotal($subtotal)
                ->setTotal($total);

            $manager->persist($sale);
        }

        $manager->flush();
    }

    /**
     * Helper to keep fixture idempotent when running with --append.
     */
    private function getOrCreate(ObjectManager $manager, string $class, array $criteria, callable $factory): object
    {
        $existing = $manager->getRepository($class)->findOneBy($criteria);
        if ($existing instanceof $class) {
            return $existing;
        }

        $entity = $factory();
        $manager->persist($entity);

        return $entity;
    }
}
