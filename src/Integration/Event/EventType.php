<?php

declare(strict_types=1);

namespace App\Integration\Event;

use App\Integration\Entity\IntegrationDocument;

/**
 * The catalog of events the integration layer can publish. This enum is the
 * contract: every value here has a documented `data` shape and may be
 * subscribed to by a webhook endpoint.
 */
enum EventType: string
{
    case DocumentAccepted = 'document.accepted';
    case DocumentRejected = 'document.rejected';
    case DocumentVoided = 'document.voided';

    public static function forDocumentStatus(string $status): ?self
    {
        return match ($status) {
            IntegrationDocument::STATUS_ACCEPTED => self::DocumentAccepted,
            IntegrationDocument::STATUS_REJECTED => self::DocumentRejected,
            IntegrationDocument::STATUS_VOIDED => self::DocumentVoided,
            default => null,
        };
    }

    /**
     * @return list<string> every publishable type, for endpoint subscription validation
     */
    public static function all(): array
    {
        return array_map(static fn(self $case): string => $case->value, self::cases());
    }

    public function matches(string $pattern): bool
    {
        if ($pattern === $this->value || '*' === $pattern) {
            return true;
        }

        // `document.*` style prefix match
        if (str_ends_with($pattern, '.*')) {
            return str_starts_with($this->value, substr($pattern, 0, -1));
        }

        return false;
    }
}
