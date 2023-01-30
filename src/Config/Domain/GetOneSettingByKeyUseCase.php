<?php

namespace App\Config\Domain;

readonly class GetOneSettingByKeyUseCase
{
    public function __construct(
        private ConfigRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(string $key): ?Config
    {
        return $this->repository->getOneByKey($key);
    }
}