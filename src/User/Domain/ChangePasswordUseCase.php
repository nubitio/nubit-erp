<?php

namespace App\User\Domain;

use App\Core\Domain\Exception\ServiceException;

readonly class ChangePasswordUseCase
{
    public function __construct(
        private UserRepositoryInterface $repository
    )
    {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->repository->getOne($userId);
        if (!password_verify($currentPassword, $user?->getPassword() ?? '')) {
            throw new ServiceException('La contraseña actual es incorrecta.');
        }
        $this->repository->changePassword($userId, $newPassword);
    }
}
