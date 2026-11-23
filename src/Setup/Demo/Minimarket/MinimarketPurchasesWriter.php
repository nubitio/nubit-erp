<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\AccountsPayable\Entity\ExpenseCategory;
use App\AccountsPayable\Entity\SupplierBill;
use App\Contacts\Entity\Supplier;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/** The restocking the owner did over the last weeks, two of the three bills still waiting to be paid. */
final readonly class MinimarketPurchasesWriter
{
    private const float IGV = 0.18;

    /**
     * Days ago, supplier position, units per product, and whether the bill is already paid.
     *
     * @var list<array{daysAgo: int, supplier: int, items: array<string, int>, paid: bool}>
     */
    private const array ORDERS = [
        [
            'daysAgo' => 16,
            'supplier' => 2,
            'items' => ['HUE12' => 30, 'YOG1' => 12, 'QUE250' => 6, 'LEC400' => 24],
            'paid' => true,
        ],
        [
            'daysAgo' => 9,
            'supplier' => 1,
            'items' => ['ARR5' => 12, 'AZU1' => 20, 'ACE1' => 12, 'FID5' => 24],
            'paid' => false,
        ],
        ['daysAgo' => 4, 'supplier' => 0, 'items' => ['INK15' => 48, 'COC15' => 24, 'AGU625' => 36], 'paid' => false],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<Supplier> $suppliers
     * @param array<string, \App\Catalog\Entity\Product> $products
     *
     * @return array<string, int> units received, by product code
     */
    public function write(
        User $buyer,
        Branch $branch,
        Warehouse $warehouse,
        array $suppliers,
        array $products,
        DateTimeImmutable $today,
    ): array {
        $expenseRepository = $this->entityManager->getRepository(ExpenseCategory::class);
        $category = $expenseRepository->findOneBy([
            'code' => 'other_operating_expense',
        ]) ?? $expenseRepository->findOneBy([]);
        if (!$category instanceof ExpenseCategory) {
            throw new RuntimeException('No hay categoría de gasto. Ejecute el setup inicial antes del seed.');
        }

        $received = [];
        foreach (self::ORDERS as $index => $order) {
            $issued = $today->modify(sprintf('-%d days', $order['daysAgo']))->setTime(10, 30);
            $purchase = $this->purchase(
                $buyer,
                $branch,
                $warehouse,
                $suppliers[$order['supplier']],
                $issued,
                4500 + $index,
            );

            $gross = 0.0;
            $line = 0;
            foreach ($order['items'] as $code => $qty) {
                $product = $products[$code];
                $cost = (float) $product->getPurchasePrice();
                $lineGross = round($cost * $qty, 2);
                $lineTax = round($lineGross * self::IGV, 2);
                $purchase->addItem(
                    new PurchaseItem()
                        ->setProduct($product)
                        ->setLineNo(++$line)
                        ->setNameSnapshot($product->getName())
                        ->setSkuSnapshot($product->getCode())
                        ->setUomSnapshot($product->getUnitOfMeasure()->getCode())
                        ->setQty((string) $qty)
                        ->setUnitCost(number_format($cost, 4, '.', ''))
                        ->setGrossAmount(number_format($lineGross, 2, '.', ''))
                        ->setTaxAmount(number_format($lineTax, 2, '.', ''))
                        ->setTotalAmount(number_format($lineGross + $lineTax, 2, '.', '')),
                );
                $gross += $lineGross;
                $received[$code] = ($received[$code] ?? 0) + $qty;
            }

            $tax = round($gross * self::IGV, 2);
            $total = $gross + $tax;
            $purchase
                ->setSubtotal(number_format($gross, 2, '.', ''))
                ->setTaxTotal(number_format($tax, 2, '.', ''))
                ->setGrandTotal(number_format($total, 2, '.', ''));
            $this->entityManager->persist($purchase);
            $this->entityManager->persist($this->bill(
                $purchase,
                $suppliers[$order['supplier']],
                $branch,
                $category,
                $issued,
                4500 + $index,
                $total,
                $order['paid'],
            ));
        }
        $this->entityManager->flush();

        return $received;
    }

    private function purchase(
        User $buyer,
        Branch $branch,
        Warehouse $warehouse,
        Supplier $supplier,
        DateTimeImmutable $issued,
        int $docNumber,
    ): Purchase {
        $stamp = DateTime::createFromImmutable($issued)->setTimezone(new DateTimeZone('UTC'));

        return new Purchase()
            ->setBranch($branch)
            ->setWarehouse($warehouse)
            ->setSupplier($supplier)
            ->setBuyerUser($buyer)
            ->setStatus('POSTED')
            ->setCurrency('PEN')
            ->setSupplierDocType('01')
            ->setSupplierDocSeries('F001')
            ->setSupplierDocNumber(sprintf('%08d', $docNumber))
            ->setIssuedAt($stamp)
            ->setCreatedAt($stamp)
            ->setNote('Reposición de mercadería');
    }

    private function bill(
        Purchase $purchase,
        Supplier $supplier,
        Branch $branch,
        ExpenseCategory $category,
        DateTimeImmutable $issued,
        int $docNumber,
        float $total,
        bool $paid,
    ): SupplierBill {
        $amount = number_format($total, 2, '.', '');

        return new SupplierBill()
            ->setSupplier($supplier)
            ->setBranch($branch)
            ->setPurchase($purchase)
            ->setCategory($category)
            ->setDocumentType('01')
            ->setSeries('F001')
            ->setNumber(sprintf('%08d', $docNumber))
            ->setIssueDate($issued)
            ->setDueDate($issued->modify('+20 days'))
            ->setAmount($amount)
            ->setPaidTotal($paid ? $amount : '0.00')
            ->setBalanceDue($paid ? '0.00' : $amount)
            ->setStatus($paid ? SupplierBill::PAID : SupplierBill::PENDING)
            ->setNote($paid ? 'Pagada' : 'Por pagar al proveedor');
    }
}
