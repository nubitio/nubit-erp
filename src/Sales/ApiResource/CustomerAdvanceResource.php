<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Sales\Dto\CustomerAdvanceReceipt;
use App\Sales\Dto\SaleReceiveAdvanceDto;
use App\Sales\State\ReceiveAdvanceProcessor;

/**
 * Receiving a customer advance (#382): money in with no target sale. Books the
 * money once and appends an `ADVANCE` entry on the customer ledger.
 */
#[ApiResource(
    shortName: 'CustomerAdvance',
    operations: [
        new Post(
            uriTemplate: '/sales/customer-advances',
            status: 201,
            input: SaleReceiveAdvanceDto::class,
            output: CustomerAdvanceReceipt::class,
            processor: ReceiveAdvanceProcessor::class,
            read: false,
            security: "is_granted('perm:sales.collect')",
            name: 'sales_receive_customer_advance',
        ),
    ],
    normalizationContext: ['groups' => ['customer_advance:read']],
    denormalizationContext: [],
)]
final class CustomerAdvanceResource {}
