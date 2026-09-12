<?php

declare(strict_types=1);

namespace App\Tests\Efact\Observability;

use Psr\Log\AbstractLogger;
use Stringable;

/** @internal test double */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $calls = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->calls[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
