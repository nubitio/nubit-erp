<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Sales\Dto\AdvanceApplicationResult;
use App\Sales\Dto\CustomerAdvanceReceipt;
use App\Sales\Dto\SaleApplyAdvanceDto;
use App\Sales\Dto\SaleReceiveAdvanceDto;
use App\Sales\State\ApplyAdvanceProcessor;
use App\Sales\State\ReceiveAdvanceProcessor;

/**
 * Customer advances (#382): receiving money with no target sale, and applying a
 * held advance to open sales.
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
        new Post(
            uriTemplate: '/sales/customer-advances/apply',
            status: 200,
            input: SaleApplyAdvanceDto::class,
            output: AdvanceApplicationResult::class,
            processor: ApplyAdvanceProcessor::class,
            read: false,
            security: "is_granted('perm:sales.collect')",
            name: 'sales_apply_customer_advance',
        ),
    ],
    normalizationContext: ['groups' => ['customer_advance:read']],
    denormalizationContext: [],
)]
final class CustomerAdvanceResource {}
