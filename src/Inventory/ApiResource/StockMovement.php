<?php

declare(strict_types=1);

namespace App\Inventory\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use App\Inventory\State\StockMovementProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only Kardex resource.
 *
 * Exposes stock_movements as a paginated collection with running_balance
 * computed via SQL window function.
 *
 * Recommended filters: warehouse (UUID), product (UUID), from/to (date), type.
 *
 * Security: authenticated users can read Kardex; stock writes remain protected
 * by the StockEntry resource.
 */
#[ApiResource(
    shortName: 'StockMovement',
    operations: [
        new GetCollection(
            uriTemplate: '/stock-movements',
            provider: StockMovementProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['stock_movement:read']],
    filters: [DataGridFilter::class],
    security: "is_granted('ROLE_USER')",
    mercure: false,
)]
final class StockMovement
{
    #[ApiProperty(identifier: true)]
    #[Groups(['stock_movement:read'])]
    public string $id;

    /** @var array{id: string, name: string}|null */
    #[Groups(['stock_movement:read'])]
    public ?array $warehouse = null;

    /** @var array{id: string, code: string|null, name: string}|null */
    #[Groups(['stock_movement:read'])]
    public ?array $product = null;

    #[Groups(['stock_movement:read'])]
    public ?string $occurredAt = null;

    #[Groups(['stock_movement:read'])]
    public ?string $movementType = null;

    #[Groups(['stock_movement:read'])]
    public ?string $movementTypeLabel = null;

    /** Positive qty when movement is an inbound (null when outbound). */
    #[Groups(['stock_movement:read'])]
    public ?string $qtyIn = null;

    /** Positive qty when movement is an outbound (null when inbound). */
    #[Groups(['stock_movement:read'])]
    public ?string $qtyOut = null;

    #[Groups(['stock_movement:read'])]
    public ?string $qtyDeltaBase = null;

    /** @var array{id: int, name: string}|null */
    #[Groups(['stock_movement:read'])]
    public ?array $unit = null;

    #[Groups(['stock_movement:read'])]
    public ?string $sourceType = null;

    #[Groups(['stock_movement:read'])]
    public ?string $sourceId = null;

    /** Human-readable document reference (serie-número) resolved from sourceType/sourceId. */
    #[Groups(['stock_movement:read'])]
    public ?string $sourceReference = null;

    #[Groups(['stock_movement:read'])]
    public ?string $note = null;

    /** Cumulative balance after this movement (running sum over time). */
    #[Groups(['stock_movement:read'])]
    public ?string $runningBalance = null;

    /** Unit cost from product purchase price (weighted-average proxy). */
    #[Groups(['stock_movement:read'])]
    public ?string $unitCost = null;

    /** Absolute movement value at unit cost. */
    #[Groups(['stock_movement:read'])]
    public ?string $lineValue = null;

    /** Running balance value at unit cost. */
    #[Groups(['stock_movement:read'])]
    public ?string $runningValue = null;

    #[Groups(['stock_movement:read'])]
    public ?string $createdAt = null;
}
