<?php

declare(strict_types=1);

namespace App\BankReconciliation\Service;

use App\AccountsPayable\Entity\SupplierPayment;
use App\BankReconciliation\Entity\BankReconciliationMatch;
use App\BankReconciliation\Entity\BankStatementLine;
use App\CashRegister\Entity\CashMovement;
use App\Identity\Entity\User;
use App\Sales\Entity\SalePayment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class BankReconciliationMatchService
{
    /** @var array<string, class-string<SupplierPayment|SalePayment|CashMovement>> */
    public const array SOURCE_TYPES = [
        'supplier_payment' => SupplierPayment::class,
        'sale_payment' => SalePayment::class,
        'cash_movement' => CashMovement::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function confirmMatch(
        BankStatementLine $line,
        SupplierPayment|SalePayment|CashMovement $source,
        User $matchedBy,
        string $matchType,
    ): BankReconciliationMatch {
        if ($line->getStatus() !== BankStatementLine::UNMATCHED) {
            throw new UnprocessableEntityHttpException('La línea del extracto ya fue conciliada o ignorada.');
        }

        $sourceField = match (true) {
            $source instanceof SupplierPayment => 'supplierPayment',
            $source instanceof SalePayment => 'salePayment',
            $source instanceof CashMovement => 'cashMovement',
        };

        $existing = $this->entityManager
            ->getRepository(BankReconciliationMatch::class)
            ->findOneBy([$sourceField => $source]);
        if ($existing !== null) {
            throw new UnprocessableEntityHttpException('El movimiento ya fue conciliado con otra línea del extracto.');
        }

        $match = new BankReconciliationMatch()
            ->setBankStatementLine($line)
            ->setMatchType($matchType)
            ->setMatchedBy($matchedBy);

        match (true) {
            $source instanceof SupplierPayment => $match->setSupplierPayment($source),
            $source instanceof SalePayment => $match->setSalePayment($source),
            $source instanceof CashMovement => $match->setCashMovement($source),
        };

        $line->setStatus(BankStatementLine::MATCHED);

        $this->entityManager->persist($match);
        $this->entityManager->flush();

        return $match;
    }

    public function undoMatch(BankStatementLine $line): void
    {
        $match = $this->entityManager
            ->getRepository(BankReconciliationMatch::class)
            ->findOneBy(['bankStatementLine' => $line]);
        if ($match === null) {
            throw new UnprocessableEntityHttpException('La línea del extracto no tiene una conciliación activa.');
        }

        $this->entityManager->remove($match);
        $line->setStatus(BankStatementLine::UNMATCHED);
        $this->entityManager->flush();
    }

    public function ignore(BankStatementLine $line): void
    {
        if ($line->getStatus() !== BankStatementLine::UNMATCHED) {
            throw new UnprocessableEntityHttpException('La línea del extracto ya fue conciliada o ignorada.');
        }

        $line->setStatus(BankStatementLine::IGNORED);
        $this->entityManager->flush();
    }
}
