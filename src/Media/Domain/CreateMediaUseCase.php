<?php

namespace App\Media\Domain;

class CreateMediaUseCase
{
    private MediaRepositoryInterface $repository;

    public function __construct(MediaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Media $media): Media
    {
        return $this->repository->insert($media);
    }
}
