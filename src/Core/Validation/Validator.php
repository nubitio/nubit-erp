<?php

declare(strict_types=1);

namespace App\Core\Validation;

use App\Core\Exception\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class Validator
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    public function validate(mixed $value, array $constraints = [], array $groups = []): void
    {
        $violations = $this->validator->validate($value, $constraints, $groups);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            throw ValidationException::withErrors($errors);
        }
    }

    public function isValid(mixed $value, array $constraints = [], array $groups = []): bool
    {
        $violations = $this->validator->validate($value, $constraints, $groups);

        return count($violations) === 0;
    }

    public function getViolations(mixed $value, array $constraints = [], array $groups = []): array
    {
        $violations = $this->validator->validate($value, $constraints, $groups);
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }
}
