<?php

declare(strict_types=1);

namespace App\Inventory\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Efact\Doctrine\Filter\DevExtremeFilter;
use App\Inventory\State\StockMovementProvider;

/**
 * Read-only Kardex resource.
 *
 * Exposes stock_movements as a paginated collection with running_balance
 * computed via SQL window function.
 *
 * Recommended filters: warehouse (UUID), product (UUID), from/to (date), type.
 *
 * Security: ROLE_ADMIN or ROLE_SUPER_ADMIN only.
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
    filters: [DevExtremeFilter::class],
    security: "is_granted('ROLE_ADMIN')",
    mercure: false,
)]
final class StockMovement
{
    #[ApiProperty(identifier: true)]
    public string $id;

    /** @var array{id: string, name: string}|null */
    public ?array $warehouse = null;

    /** @var array{id: string, code: string|null, name: string}|null */
    public ?array $product = null;

    public ?string $occurredAt = null;

    public ?string $movementType = null;

    public ?string $movementTypeLabel = null;

    /** Positive qty when movement is an inbound (null when outbound). */
    public ?string $qtyIn = null;

    /** Positive qty when movement is an outbound (null when inbound). */
    public ?string $qtyOut = null;

    public ?string $qtyDeltaBase = null;

    /** @var array{id: int, name: string}|null */
    public ?array $unit = null;

    public ?string $sourceType = null;

    public ?string $sourceId = null;

    public ?string $note = null;

    /** Cumulative balance after this movement (running sum over time). */
    public ?string $runningBalance = null;

    public ?string $createdAt = null;
}
