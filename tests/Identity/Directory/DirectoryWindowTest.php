<?php

declare(strict_types=1);

namespace App\Tests\Identity\Directory;

use App\Identity\Directory\DirectoryWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectoryWindowTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, array{int, int, int, int}}> */
    public static function pages(): iterable
    {
        // offset, per page, invitations => [invitation offset, invitation limit, user offset, user limit]
        yield 'first page holds the invitations, then users' => [0, 10, 3, [0, 3, 0, 7]];
        yield 'no invitations: only users' => [0, 10, 0, [0, 0, 0, 10]];
        yield 'invitations spill over a short page' => [0, 2, 5, [0, 2, 0, 0]];
        yield 'second page finishes the invitations' => [2, 2, 3, [2, 1, 0, 1]];
        yield 'later pages are users only, shifted by the invitations' => [20, 10, 3, [3, 0, 17, 10]];
    }

    /**
     * @param array{int, int, int, int} $expected
     */
    #[DataProvider('pages')]
    public function testSplitsAPageBetweenInvitationsAndUsers(
        int $offset,
        int $perPage,
        int $invitations,
        array $expected,
    ): void {
        $window = DirectoryWindow::for($offset, $perPage, $invitations);

        static::assertSame($expected, [
            $window->invitationOffset,
            $window->invitationLimit,
            $window->userOffset,
            $window->userLimit,
        ]);
    }
}
