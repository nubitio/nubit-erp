<?php

namespace App\ComboOption\Domain;

class UpdateComboOptionUseCase
{
    private ComboOptionRepositoryInterface $repository;

    public function __construct(ComboOptionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(ComboOption $comboOption): ComboOption
    {
        return $this->repository->update($comboOption);
    }
}