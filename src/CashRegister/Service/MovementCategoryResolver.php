<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use App\CashRegister\Entity\CashMovement;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The reason a movement registered by hand is filed under. A person normally says only the kind (income, expense or
 * withdrawal) and writes the reason; the system files it under its own category. A manual category is still accepted.
 */
readonly class MovementCategoryResolver
{
    /** The reason the system files each kind of manual movement under. */
    private const array KIND_CATEGORIES = [
        'income' => 'other_income',
        'expense' => 'petty_cash_expense',
        'withdrawal' => 'manager_withdrawal',
    ];

    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception
     */
    public function resolve(CashMovement $movement): int
    {
        $category = $movement->getCategory();
        if ($category !== null && $category->getId() !== null) {
            // Sales, collections, refunds and count differences are recorded by the system itself; letting a person
            // pick them would count the same money twice.
            if (!$category->isManual()) {
                throw new UnprocessableEntityHttpException(
                    'Ese motivo lo registra el sistema. Elige un ingreso o egreso manual.',
                );
            }

            return $category->getId();
        }

        $code = self::KIND_CATEGORIES[$movement->getKind() ?? ''] ?? null;
        if ($code === null) {
            throw new UnprocessableEntityHttpException('Indica el tipo de movimiento: ingreso, egreso o retiro.');
        }

        $found = $this->connection->fetchOne('SELECT id FROM cash_movement_category WHERE code = ?', [$code]);
        if ($found === false || $found === null) {
            throw new UnprocessableEntityHttpException('No existe el motivo de caja para ese tipo de movimiento.');
        }

        return (int) $found;
    }
}
