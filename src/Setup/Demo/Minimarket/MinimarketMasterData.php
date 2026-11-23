<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Entity\UnitOfMeasure;
use App\Contacts\Entity\Customer;
use App\Contacts\Entity\Supplier;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;

/** Creates the things a minimarket has before the first sale: staff, catalogue, customers and suppliers. */
final readonly class MinimarketMasterData
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return array<string, Product> keyed by product code
     */
    public function products(UnitOfMeasure $unit, Warehouse $warehouse): array
    {
        $categories = [];
        foreach (MinimarketCatalog::categories() as $name) {
            $category = $this->entityManager
                ->getRepository(Category::class)
                ->findOneBy(['name' => $name]) ?? new Category()->setName($name);
            $this->entityManager->persist($category);
            $categories[$name] = $category;
        }

        $products = [];
        foreach (MinimarketCatalog::products() as $row) {
            $product = new Product()
                ->setCode($row['code'])
                ->setName($row['name'])
                ->setType(1)
                ->setCategory($categories[$row['category']])
                ->setUnitOfMeasure($unit)
                ->setPurchasePrice($row['cost'])
                ->setSalePrice($row['price'])
                ->setMinStock((string) $row['minStock']);
            $this->entityManager->persist($product);

            // The quantity is written once the whole story is known; see MinimarketDemoSeeder.
            $this->entityManager->persist(
                new ProductStock()
                    ->setProduct($product)
                    ->setWarehouse($warehouse)
                    ->setStock(0.0)
                    ->setMinStock((float) $row['minStock']),
            );
            $products[$row['code']] = $product;
        }
        $this->entityManager->flush();

        return $products;
    }

    /** @return array<string, Customer> keyed by the customer key used in the story */
    public function customers(): array
    {
        $customers = [];
        foreach (MinimarketCatalog::customers() as $row) {
            $customer = new Customer()
                ->setRuc($row['document'])
                ->setBusinessName($row['name'])
                ->setFiscalAddress($row['address'])
                ->setPhone($row['phone'])
                ->setEmail($row['email']);
            $this->entityManager->persist($customer);
            $customers[$row['key']] = $customer;
        }
        $this->entityManager->flush();

        return $customers;
    }

    /** @return list<Supplier> */
    public function suppliers(): array
    {
        $suppliers = [];
        foreach (MinimarketCatalog::suppliers() as $row) {
            $supplier = new Supplier()
                ->setRuc($row['ruc'])
                ->setBusinessName($row['name'])
                ->setFiscalAddress($row['address'])
                ->setPhone($row['phone']);
            $this->entityManager->persist($supplier);
            $suppliers[] = $supplier;
        }
        $this->entityManager->flush();

        return $suppliers;
    }

    /** @return array<string, PaymentMethod> keyed by name */
    public function paymentMethods(): array
    {
        $methods = [];
        foreach ($this->entityManager->getRepository(PaymentMethod::class)->findAll() as $method) {
            $methods[$method->getName()] = $method;
        }

        return $methods;
    }
}
