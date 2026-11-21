<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use RuntimeException;

/** A profile change the user can fix: one message per field, in the shape the web forms already render. */
final class ProfileViolations extends RuntimeException
{
    /** @param array<string, string> $violations field => message */
    public function __construct(
        public readonly array $violations,
    ) {
        parent::__construct(implode(' ', $violations));
    }

    public static function of(string $field, string $message): self
    {
        return new self([$field => $message]);
    }
}
