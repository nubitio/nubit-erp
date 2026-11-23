<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\AccountsPayable\Entity\ExpenseCategory;
use App\AccountsPayable\Entity\SupplierBill;
use App\Contacts\Entity\Supplier;
use App\Organization\Entity\Branch;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * The shop's fixed costs: rent and electricity for the last two months. Without them the profit
 * screen would show gross profit only, and an owner's real question is what is left after paying the bills.
 */
final readonly class MinimarketExpensesWriter
{
    /**
     * Months ago, day of the month, supplier position (after the goods suppliers), category, amount, paid.
     *
     * @var list<array{monthsAgo: int, day: int, supplier: int, category: string, amount: float, paid: bool, note: string}>
     */
    private const array BILLS = [
        [
            'monthsAgo' => 1,
            'day' => 1,
            'supplier' => 3,
            'category' => 'rent',
            'amount' => 1500.0,
            'paid' => true,
            'note' => 'Alquiler de octubre',
        ],
        [
            'monthsAgo' => 1,
            'day' => 10,
            'supplier' => 4,
            'category' => 'utilities',
            'amount' => 236.0,
            'paid' => true,
            'note' => 'Luz de octubre',
        ],
        [
            'monthsAgo' => 0,
            'day' => 1,
            'supplier' => 3,
            'category' => 'rent',
            'amount' => 1500.0,
            'paid' => true,
            'note' => 'Alquiler de noviembre',
        ],
        [
            'monthsAgo' => 0,
            'day' => 10,
            'supplier' => 4,
            'category' => 'utilities',
            'amount' => 248.0,
            'paid' => false,
            'note' => 'Luz de noviembre',
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<Supplier> $suppliers goods suppliers first, then the landlord and the electricity company
     *
     * @return int bills created
     */
    public function write(Branch $branch, array $suppliers, DateTimeImmutable $today): int
    {
        $categories = [];
        foreach ($this->entityManager->getRepository(ExpenseCategory::class)->findAll() as $category) {
            $categories[$category->getCode()] = $category;
        }

        foreach (self::BILLS as $index => $bill) {
            $issued = $today
                ->modify('first day of this month')
                ->modify(sprintf('-%d months', $bill['monthsAgo']))
                ->modify(sprintf('+%d days', $bill['day'] - 1));
            if ($issued > $today) {
                continue; // a bill dated in the future does not exist yet
            }
            $this->entityManager->persist($this->bill(
                $branch,
                $suppliers[$bill['supplier']],
                $categories[$bill['category']] ?? throw new RuntimeException(
                    'Falta la categoría de gasto ' . $bill['category'],
                ),
                $issued,
                7001 + $index,
                $bill,
            ));
        }
        $this->entityManager->flush();

        return count(self::BILLS);
    }

    /** @param array{monthsAgo: int, day: int, supplier: int, category: string, amount: float, paid: bool, note: string} $bill */
    private function bill(
        Branch $branch,
        Supplier $supplier,
        ExpenseCategory $category,
        DateTimeImmutable $issued,
        int $number,
        array $bill,
    ): SupplierBill {
        $amount = number_format($bill['amount'], 2, '.', '');

        return new SupplierBill()
            ->setSupplier($supplier)
            ->setBranch($branch)
            ->setCategory($category)
            ->setDocumentType('01')
            ->setSeries('F001')
            ->setNumber(sprintf('%08d', $number))
            ->setIssueDate($issued)
            ->setDueDate($issued->modify('+20 days'))
            ->setAmount($amount)
            ->setPaidTotal($bill['paid'] ? $amount : '0.00')
            ->setBalanceDue($bill['paid'] ? '0.00' : $amount)
            ->setStatus($bill['paid'] ? SupplierBill::PAID : SupplierBill::PENDING)
            ->setNote($bill['note']);
    }
}
