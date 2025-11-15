<?php

declare(strict_types=1);

namespace App\Core\Exception;

class ValidationException extends ServiceException
{
    private array $errors = [];

    public static function withErrors(array $errors): self
    {
        $exception = new self('Validation failed');
        $exception->errors = $errors;

        return $exception;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
