<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\UnitOfMeasure;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Branch;
use App\Entity\Company;
use App\Entity\LogisticOperation;
use App\Entity\PaymentMethod;
use App\Entity\PriceList;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Supplier;
use App\Entity\Warehouse;
use App\Entity\VoucherType;
use App\Repository\InvoiceRepository;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    private const array VOUCHER_TYPES = [
        '01' => 'Factura',
        '03' => 'Boleta',
    ];

    private const array SAMPLE_CUSTOMERS = [
        [
            'ruc' => '20123456789',
            'businessName' => 'Acme SAC',
            'email' => 'facturas+acme@example.com',
        ],
        [
            'ruc' => '10987654321',
            'businessName' => 'Contoso SRL',
            'email' => 'facturas+contoso@example.com',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::VOUCHER_TYPES as $code => $name) {
            $voucherType = $manager->getRepository(VoucherType::class)->findOneBy(['code' => $code]);
            if (!$voucherType instanceof VoucherType) {
                $voucherType = new VoucherType()
                    ->setCode($code)
                    ->setName($name);
                $manager->persist($voucherType);
            }
        }

        $customers = [];
        foreach (self::SAMPLE_CUSTOMERS as $customerData) {
            $customer = $manager->getRepository(Customer::class)->find($customerData['ruc']);
            if (!$customer instanceof Customer) {
                $customer = new Customer()
                    ->setRuc($customerData['ruc'])
                    ->setBusinessName($customerData['businessName'])
                    ->setEmail($customerData['email'])
                    ->setFiscalAddress('Av. Principal 123')
                    ->setPhone('+51 999 999 999');

                $manager->persist($customer);
            }

            $customers[] = $customer;
        }

        /** @var InvoiceRepository $invoiceRepository */
        $invoiceRepository = $manager->getRepository(Invoice::class);
        $existingCount = $invoiceRepository->count([]);

        // Ensure fixtures product exists once to avoid duplicates on append.
        $fixturesProduct = $manager->getRepository(Product::class)->findOneBy(['code' => 'FIXTURE']);
        if (!$fixturesProduct instanceof Product) {
            $unit = $this->getOrCreate(
                $manager,
                UnitOfMeasure::class,
                ['code' => 'NIU'],
                static fn (): UnitOfMeasure => new UnitOfMeasure()
                    ->setName('Unidad')
                    ->setCode('NIU')
                    ->setType(1)
                    ->setRatio(1.0)
                    ->setReference(true)
            );

            $category = $this->getOrCreate(
                $manager,
                Category::class,
                ['name' => 'Fixtures'],
                static fn (): Category => new Category()->setName('Fixtures')
            );

            $fixturesProduct = new Product()
                ->setCode('FIXTURE')
                ->setName('Fixture item')
                ->setType(1)
                ->setCategory($category)
                ->setUnitOfMeasure($unit)
                ->setPurchasePrice(10.0)
                ->setSalePrice(12.0)
                ->setStock(100)
                ->setMinStock(5);
            $manager->persist($fixturesProduct);
        }

        for ($i = 0; $i < 10; $i++) {
            $customer = $customers[$i % count($customers)];
            $typeCode = array_keys(self::VOUCHER_TYPES)[$i % count(self::VOUCHER_TYPES)];
            $issuedAt = new DateTimeImmutable(sprintf('-%d minutes', random_int(30, 720)));
            $series = $typeCode === '01' ? 'F001' : 'B001';
            $number = sprintf('%08d', 500 + $existingCount + $i);

            $sale = $this->createSale($manager, $customer, $fixturesProduct, $issuedAt, $i, $series, $number);
            if ($sale->getId() === null) {
                $manager->flush();
            }

            $existingInvoice = $manager->getRepository(Invoice::class)->findOneBy([
                'sale' => $sale,
            ]);

            if ($existingInvoice instanceof Invoice) {
                continue;
            }

            $invoice = new Invoice();
            $invoice->setSale($sale);
            $invoice->setRawDocumentId(5000 + $i);
            $invoice->setCustomer($customer);
            $invoice->setVoucherTypeId($typeCode);
            $invoice->setIssuedAt($issuedAt);
            $invoice->setSeries($series);
            $invoice->setNumber($number);
            $invoice->setUnaffected(false);
            $invoice->setSubTotal($sale->getSubtotal());
            $invoice->setTaxableAmount($sale->getSubtotal());
            $invoice->setIgv($sale->getTotal() - $sale->getSubtotal());
            $invoice->setRc(0.0);
            $invoice->setTotal($sale->getTotal());
            $invoice->setDiscountPercent(0);
            $invoice->setDiscountAmount(0.0);
            $invoice->setVoided(0);
            $invoice->setDigest('digest-' . (1000 + $i));
            $invoice->setVoidTicket('');
            $invoice->setVoidCorrelation('');
            $invoice->setSunatStatus('PENDING');
            $invoice->setSunatMessage('Pending to send');
            $invoice->setCreatedAt($issuedAt);
            $invoice->setUpdatedAt($issuedAt);

            $manager->persist($invoice);
        }

        $manager->flush();
    }

    private function createSale(
        ObjectManager $manager,
        Customer $customer,
        Product $product,
        DateTimeImmutable $issuedAt,
        int $index,
        string $series,
        string $number
    ): Sale {
        $logisticOperation = $this->getOrCreate(
            $manager,
            LogisticOperation::class,
            ['name' => 'Venta'],
            static fn (): LogisticOperation => new LogisticOperation()
                ->setName('Venta')
                ->setType(1)
        );

        $warehouse = $this->getOrCreate(
            $manager,
            Warehouse::class,
            ['name' => 'Almacén Fixtures'],
            function () use ($manager): Warehouse {
                $branch = $manager->getRepository(Branch::class)->findOneBy([]);
                if (!$branch instanceof Branch) {
                    $company = $this->getOrCreate(
                        $manager,
                        Company::class,
                        ['ruc' => '20600000001'],
                        static fn (): Company => new Company()
                            ->setRuc('20600000001')
                            ->setBusinessName('Efact Demo SAC')
                            ->setTradeName('Efact Demo')
                            ->setFiscalAddress('Av. Demo 123 Lima')
                            ->setPhone('012345678')
                    );

                    $branch = new Branch()
                        ->setName('Local Fixtures')
                        ->setAddress('Av. Demo 123 Lima')
                        ->setPhone('012345678')
                        ->setCompany($company);
                    $manager->persist($branch);
                }

                return new Warehouse()
                    ->setName('Almacén Fixtures')
                    ->setBranch($branch);
            }
        );
        if ($warehouse->getBranch() === null) {
            $branch = $manager->getRepository(Branch::class)->findOneBy([]);
            if ($branch instanceof Branch) {
                $warehouse->setBranch($branch);
            }
        }

        $priceList = $this->getOrCreate(
            $manager,
            PriceList::class,
            ['name' => 'Lista Fixtures'],
            static fn (): PriceList => new PriceList()->setName('Lista Fixtures')
        );

        $paymentMethod = $this->getOrCreate(
            $manager,
            PaymentMethod::class,
            ['name' => 'Efectivo'],
            static fn (): PaymentMethod => new PaymentMethod()->setName('Efectivo')
        );

        $supplier = $this->getOrCreate(
            $manager,
            Supplier::class,
            ['ruc' => '20567890123'],
            static fn (): Supplier => new Supplier()
                ->setRuc('20567890123')
                ->setBusinessName('Proveedor Fixtures SAC')
                ->setFiscalAddress('Av. Industrial 456 Lima')
                ->setPhone('+51 912345678')
        );

        $sale = new Sale();
        $sale
            ->setOperacion($logisticOperation)
            ->setAlmacen($warehouse)
            ->setCliente($customer)
            ->setPriceList($priceList)
            ->setTipoId(1)
            ->setFecha($issuedAt)
            ->setProveedor($supplier)
            ->setFormaPago(1)
            ->setTipoPago($paymentMethod)
            ->setTipoTarjeta(0)
            ->setSerie($series)
            ->setNumero($number)
            ->setDescuento(0.0)
            ->setGlosa('Venta fixture #' . ($index + 1))
            ->setOrdenId($index + 1)
            ->setHasInvoice(true)
            ->setIsConsume(false)
            ->setCajaId(1)
            ->setEstado(1)
            ->setDateCreated($issuedAt)
            ->setDateUpdated($issuedAt)
            ->setCreatedBy(1)
            ->setUpdatedBy(1)
            ->setIgv(18)
            ->setRecargoConsumo(0)
            ->setInvoice('PENDING');

        $qty = 1;
        $unitPrice = $product->getSalePrice() ?? 12.0;
        $lineTotal = round($unitPrice, 2);
        $igvAmount = round($lineTotal * 0.18, 2);

        $saleItem = new SaleItem();
        $saleItem->setMovimiento($sale);
        $saleItem->setProducto($product);
        $saleItem->setCantidad($qty);
        $saleItem->setPrecioUnitario($unitPrice);
        $saleItem->setDescuento(0.0);
        $saleItem->setPrecioTotal($lineTotal);

        $manager->persist($saleItem);
        $sale->addItem($saleItem);

        $sale
            ->setSubtotal($lineTotal)
            ->setTotal($lineTotal + $igvAmount);

        $manager->persist($sale);

        return $sale;
    }

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

    public function getDependencies(): array
    {
        return [SampleDataFixtures::class];
    }
}
