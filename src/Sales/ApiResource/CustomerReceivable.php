<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Sales\State\CustomerReceivableProvider;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only "Deudores" summary: one row per customer with an open credit-sale
 * balance, aggregated from `sales` (#409). Backs the Cuentas por cobrar
 * landing grid; per-invoice detail stays on the `Sale` resource.
 */
#[ApiResource(
    shortName: 'CustomerReceivable',
    operations: [
        new GetCollection(
            uriTemplate: '/sales/reports/receivable-customers',
            provider: CustomerReceivableProvider::class,
            security: "is_granted('perm:sales.view')",
        ),
    ],
    normalizationContext: ['groups' => ['customer_receivable:read']],
    filters: [DataGridFilter::class],
    mercure: false,
)]
final class CustomerReceivable
{
    #[ApiProperty(identifier: true)]
    #[Groups(['customer_receivable:read'])]
    public string $id;

    #[Groups(['customer_receivable:read'])]
    public int $customerId;

    #[Groups(['customer_receivable:read'])]
    public string $customerName;

    #[Groups(['customer_receivable:read'])]
    public ?string $customerPhone = null;

    #[Groups(['customer_receivable:read'])]
    public string $totalBalance;

    #[Groups(['customer_receivable:read'])]
    public string $currency;

    #[Groups(['customer_receivable:read'])]
    public ?string $earliestDueDate = null;

    /** Days past due for the oldest open invoice; negative when not yet due. */
    #[Groups(['customer_receivable:read'])]
    public ?int $overdueDays = null;

    /** One of: current, 1-30, 31-60, 61-90, 90+ — days past the due date. */
    #[Groups(['customer_receivable:read'])]
    public string $agingBucket;

    #[Groups(['customer_receivable:read'])]
    public int $pendingCount;
}
