<?php

declare(strict_types=1);

namespace App\Core\Validator;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class UniqueEntity extends Constraint
{
    public string $message = 'This value is already used.';

    public function __construct(
        /** @var array<int, string> */
        public array $fields,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct(options: null, groups: $groups, payload: $payload);
        $this->message = $message ?? $this->message;
    }

    #[Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
