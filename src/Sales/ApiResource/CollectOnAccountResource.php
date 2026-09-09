<?php

declare(strict_types=1);

namespace App\Sales\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Sales\Dto\CollectOnAccountResult;
use App\Sales\Dto\SaleCollectOnAccountDto;
use App\Sales\State\CollectOnAccountProcessor;

/**
 * "Cobro a cuenta" (#381): one payment from a customer, applied FIFO across
 * their open sales or over an explicit list. Books the money once; appends one
 * `sale_payments` row per sale settled.
 */
#[ApiResource(
    shortName: 'CollectOnAccount',
    operations: [
        new Post(
            uriTemplate: '/sales/collect-on-account',
            status: 200,
            input: SaleCollectOnAccountDto::class,
            output: CollectOnAccountResult::class,
            processor: CollectOnAccountProcessor::class,
            read: false,
            security: "is_granted('perm:sales.collect')",
            name: 'sales_collect_on_account',
        ),
    ],
    normalizationContext: ['groups' => ['collect_on_account:read']],
    denormalizationContext: [],
)]
final class CollectOnAccountResource {}
