<?php

declare(strict_types=1);

namespace App\Identity\Directory;

use App\Identity\Invitation\PendingInvitation;

/**
 * Applies a grid query to pending invitations, which live outside the user table and so cannot go
 * through the database filter. It follows what that filter does for users: fields the grid does not
 * know are ignored, a missing value never satisfies a comparison, and text compares without case.
 */
final class InvitationMatcher
{
    /** Columns of the users grid; an invitation has no display name, phone or sign-in yet. */
    private const array KNOWN_FIELDS = ['username', 'email', 'role', 'displayName', 'phone', 'lastLoginAt'];

    public function matches(PendingInvitation $invitation, DirectoryQuery $query): bool
    {
        foreach ($query->leaves as [$field, $operator, $needle]) {
            if ($field === 'status' || !in_array($field, self::KNOWN_FIELDS, strict: true)) {
                continue;
            }
            if (!self::satisfies($this->valueOf($invitation, $field), $operator, $needle)) {
                return false;
            }
        }

        if ($query->search === null || $query->searchFields === []) {
            return true;
        }
        foreach ($query->searchFields as $field) {
            $value = in_array($field, self::KNOWN_FIELDS, strict: true) ? $this->valueOf($invitation, $field) : null;
            if ($value !== null && stripos($value, $query->search) !== false) {
                return true;
            }
        }

        return false;
    }

    private function valueOf(PendingInvitation $invitation, string $field): ?string
    {
        return match ($field) {
            'username', 'email' => $invitation->email(),
            'role' => ($id = $invitation->role?->getId()) === null ? null : (string) $id,
            default => null,
        };
    }

    private static function satisfies(?string $value, string $operator, mixed $needle): bool
    {
        if ($operator === 'isnull') {
            return $value === null;
        }
        if ($operator === 'isnotnull') {
            return $value !== null;
        }
        if ($value === null) {
            return false;
        }

        // A relation filter may carry an IRI (`/api/roles/3`); the id is what an invitation has.
        $normalize = static fn(mixed $item): string => is_scalar($item) ? basename((string) $item) : '';
        $text = $normalize($needle);

        return match ($operator) {
            'contains' => stripos($value, $text) !== false,
            'notcontains' => stripos($value, $text) === false,
            'startswith' => stripos($value, $text) === 0,
            'endswith' => $text !== '' && strcasecmp(substr($value, -strlen($text)), $text) === 0,
            '=' => strcasecmp($value, $text) === 0,
            '<>', '!=' => strcasecmp($value, $text) !== 0,
            'in' => in_array(
                strtolower($value),
                array_map(
                    static fn(mixed $item): string => strtolower($normalize($item)),
                    is_array($needle) ? $needle : [$needle],
                ),
                strict: true,
            ),
            default => false,
        };
    }
}
