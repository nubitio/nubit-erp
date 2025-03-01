<?php

namespace App\Config\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

class ConfigGetAllUseCase
{
    public function __construct(private readonly ConfigRepositoryInterface $repository)
    {
    }

    /**
     * @return PaginatorInterface<Config>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
