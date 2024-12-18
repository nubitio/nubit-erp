<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Cashbox\Domain\Cashbox;

#[ApiResource(
    uriTemplate: '/cashboxes/open',
    shortName: 'Cashbox',
    operations: [
        new Post(
            input: OpenCashboxDto::class,
            output: Cashbox::class,
            processor: OpenCashboxProcessor::class,
        ),
    ],
)]
class OpenCashboxDto
{
    public float $amount;
    public string $branch_id;
    public int $user_id;
}
