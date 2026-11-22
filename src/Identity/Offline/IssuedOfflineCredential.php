<?php

declare(strict_types=1);

namespace App\Identity\Offline;

use DateTimeImmutable;

/** What a till receives to sign offline sales with. `secret` is base64 and never leaves the user's own session. */
final readonly class IssuedOfflineCredential
{
    public function __construct(
        public string $id,
        public int $userId,
        #[\SensitiveParameter]
        public string $secret,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    /** @return array{id: string, userId: int, secret: string, issuedAt: string, expiresAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'secret' => $this->secret,
            'issuedAt' => $this->issuedAt->format(\DATE_ATOM),
            'expiresAt' => $this->expiresAt->format(\DATE_ATOM),
        ];
    }
}
