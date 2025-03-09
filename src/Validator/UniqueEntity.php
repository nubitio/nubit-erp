<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class UniqueEntity extends Constraint
{
    public array $fields = [];
    public string $message = 'This value is already used.';

    public function __construct(array $fields, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct([], $groups, $payload);

        $this->fields = $fields;
        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string | array
    {
        return self::CLASS_CONSTRAINT;
    }
}
