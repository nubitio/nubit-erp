<?php

declare(strict_types=1);

namespace App\Core\Exception;

class ServiceException extends \RuntimeException
{
    public static function create(string $message, int $code = 0, ?\Throwable $previous = null): self
    {
        return new self($message, $code, $previous);
    }
}
