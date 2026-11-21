<?php

declare(strict_types=1);

namespace App\Identity\Directory;

/**
 * Which rows a page of the directory takes from each side. Pending invitations come first and the
 * users follow, so page N is an invitation slice and then a user slice, with no need to load either
 * list whole: `offset`/`perPage` are the page's place in the combined list.
 */
final readonly class DirectoryWindow
{
    private function __construct(
        public int $invitationOffset,
        public int $invitationLimit,
        public int $userOffset,
        public int $userLimit,
    ) {}

    public static function for(int $offset, int $perPage, int $invitations): self
    {
        $invitationOffset = min($offset, $invitations);
        $invitationLimit = min($perPage, $invitations - $invitationOffset);

        return new self(
            invitationOffset: $invitationOffset,
            invitationLimit: $invitationLimit,
            userOffset: max(0, $offset - $invitations),
            userLimit: $perPage - $invitationLimit,
        );
    }
}
