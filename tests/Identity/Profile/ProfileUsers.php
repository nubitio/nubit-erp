<?php

declare(strict_types=1);

namespace App\Tests\Identity\Profile;

use App\Identity\Entity\User;
use ReflectionProperty;

/** Builds the users and 1x1 image the profile tests share. */
final class ProfileUsers
{
    public static function user(int $id = 1): User
    {
        $user = new User();
        $user->setUsername('ana');
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);
        // Never read: the hasher is faked, but the property is typed and must be initialised.
        $user->setPassword('hash');

        return $user;
    }

    public static function png(): string
    {
        // 1x1 transparent PNG.
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true,
        );
    }
}
