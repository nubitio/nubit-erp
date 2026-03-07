<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

/**
 * DTO for cash session movement request/response.
 *
 * Used by CashSessionMovementsController for GET list and POST create.
 * Also used as response shape for PUT update.
 */
final class CashSessionMovementDto
{
    /**
     * @param int|null                        $id          Movement ID (null on create request)
     * @param string                          $type        'income' or 'expense'
     * @param string                          $amount      Decimal string e.g. "123.45"
     * @param string|null                     $description Optional description
     * @param array{id: int, name: string}|null $category  Category object with id + name
     * @param string|null                     $createdAt   ISO-8601 datetime string (response only)
     */
    public function __construct(
        public ?int $id = null,
        public string $type = 'income',
        public string $amount = '0.00',
        public ?string $description = null,
        public ?array $category = null,
        public ?string $createdAt = null,
    ) {
    }

    /**
     * Build a DTO from a DBAL result row.
     *
     * @param array{
     *   id: int|string,
     *   movement_type: string,
     *   amount: string,
     *   description: string|null,
     *   created_at: string,
     *   category_id: int|string|null,
     *   category_name: string|null
     * } $row
     */
    public static function fromRow(array $row): self
    {
        $type = $row['movement_type'] === 'CASH_IN' ? 'income' : 'expense';

        $category = null;
        if ($row['category_id'] !== null) {
            $category = [
                'id'   => (int) $row['category_id'],
                'name' => (string) ($row['category_name'] ?? ''),
            ];
        }

        return new self(
            id: (int) $row['id'],
            type: $type,
            amount: number_format((float) $row['amount'], 2, '.', ''),
            description: isset($row['description']) ? (string) $row['description'] : null,
            category: $category,
            createdAt: $row['created_at'],
        );
    }
}
