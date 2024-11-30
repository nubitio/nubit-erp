<?php

namespace App\Menu\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

readonly class MenuProvider implements ProviderInterface
{
    public function __construct(
        private MenuBuilder $menuBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->menuBuilder->buildMenuForRole(intval($uriVariables['role_id']));
        }

        return null;
    }
}
