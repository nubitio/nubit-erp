<?php

declare(strict_types=1);

namespace App\Identity\Profile;

use App\Identity\Entity\User;

/** How a user's profile is shown to its owner. The photo's storage path never leaves the server. */
final class ProfileView
{
    /** @return array<string, mixed> */
    public static function of(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUserIdentifier(),
            'displayName' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'avatarUrl' => self::avatarUrl($user),
            'preferences' => self::preferences($user),
        ];
    }

    /** `?v=` changes with the photo, so the browser never shows a stale one after an upload. */
    public static function avatarUrl(User $user): ?string
    {
        $updatedAt = $user->getAvatarUpdatedAt();

        return $user->getAvatarPath() === null || $updatedAt === null
            ? null
            : '/api/profile/avatar?v=' . $updatedAt->getTimestamp();
    }

    /**
     * `null` means "never chosen on the account": the browser then keeps its own setting and offers it
     * to the account, instead of the account's default overwriting what the person already picked.
     *
     * @return array{density: string|null, theme: string|null}
     */
    public static function preferences(User $user): array
    {
        $stored = $user->getPreferences();

        return [
            'density' => self::oneOf($stored['density'] ?? null, ProfileInputValidator::DENSITIES),
            'theme' => self::oneOf($stored['theme'] ?? null, ProfileInputValidator::THEMES),
        ];
    }

    /** @param list<string> $allowed */
    private static function oneOf(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}
