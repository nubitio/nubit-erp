<?php

namespace App\RecipeDetail\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Recipe\Api\RecipeDto;
use App\RecipeDetail\Domain\RecipeDetail;

#[ApiResource(
    uriTemplate: '/recipes/{id}/details',
    shortName: 'RecipeDetail',
    operations: [new GetCollection()],
    uriVariables: [
        'id' => new Link(
            fromProperty: 'id',
            fromClass: RecipeDto::class,
        ),
    ],
    provider: RecipeDetailProvider::class
)]
class RecipeDetailDto extends RecipeDetail
{
}
