<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordInput
{
    #[Assert\NotBlank(message: 'La contraseña actual es requerida.')]
    public string $currentPassword = '';

    #[Assert\NotBlank(message: 'La nueva contraseña es requerida.')]
    #[Assert\Length(min: 6, max: 255, minMessage: 'La nueva contraseña debe tener al menos {{ limit }} caracteres.')]
    public string $newPassword = '';
}
