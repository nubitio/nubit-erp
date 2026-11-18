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

    /** Not an entity: a Treasury movement is a row of another context's ledger, matched by its uuid. */
    public const string TREASURY_SOURCE_TYPE = 'treasury_movement';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?TreasuryReconciliationSource $treasury = null,
    ) {}

    /**
     * Reconciles a statement line with a Treasury movement. The movement must be on the Treasury account of the
     * line's own bank account and must not be reconciled with another line.
     */
    public function confirmTreasuryMatch(
        BankStatementLine $line,
        string $movementId,
        User $matchedBy,
        string $matchType,
    ): BankReconciliationMatch {
        if ($line->getStatus() !== BankStatementLine::UNMATCHED) {
            throw new UnprocessableEntityHttpException('La línea del extracto ya fue conciliada o ignorada.');
        }
        $bankAccountId = $line->getBankAccount()->getId();
        if (
            $this->treasury === null
            || $bankAccountId === null
            || !$this->treasury->belongsToBankAccount($movementId, $bankAccountId)
        ) {
            throw new UnprocessableEntityHttpException(
                'El movimiento no pertenece a la cuenta bancaria de esta línea.',
            );
        }
        $existing = $this->entityManager
            ->getRepository(BankReconciliationMatch::class)
            ->findOneBy(['treasuryMovementId' => $movementId]);
        if ($existing !== null) {
            throw new UnprocessableEntityHttpException('El movimiento ya fue conciliado con otra línea del extracto.');
        }

        $match = new BankReconciliationMatch()
            ->setBankStatementLine($line)
            ->setMatchType($matchType)
            ->setMatchedBy($matchedBy)
            ->setTreasuryMovementId($movementId);
        $line->setStatus(BankStatementLine::MATCHED);
        $this->entityManager->persist($match);
        $this->entityManager->flush();

        return $match;
    }

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
            $source instanceof CashMovement => 'cashMovementId',
        };
        $sourceValue = $source;
        if ($source instanceof CashMovement) {
            $sourceValue = $source->getId();
            if ($sourceValue === null) {
                throw new UnprocessableEntityHttpException('El movimiento de caja no tiene un identificador válido.');
            }
        }

        $existing = $this->entityManager
            ->getRepository(BankReconciliationMatch::class)
            ->findOneBy([$sourceField => $sourceValue]);
        if ($existing !== null) {
            throw new UnprocessableEntityHttpException('El movimiento ya fue conciliado con otra línea del extracto.');
        }

        $match = new BankReconciliationMatch()
            ->setBankStatementLine($line)
            ->setMatchType($matchType)
            ->setMatchedBy($matchedBy);

        if ($source instanceof SupplierPayment) {
            $match->setSupplierPayment($source);
        } elseif ($source instanceof SalePayment) {
            $match->setSalePayment($source);
        } elseif ($source instanceof CashMovement) {
            $cashMovementId = $source->getId();
            if ($cashMovementId === null) {
                throw new UnprocessableEntityHttpException('El movimiento de caja no tiene un identificador válido.');
            }
            $match->setCashMovementId($cashMovementId);
        }

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
