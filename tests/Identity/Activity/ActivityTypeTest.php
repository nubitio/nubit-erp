<?php

declare(strict_types=1);

namespace App\Tests\Identity\Activity;

use App\Identity\Activity\ActivityType;
use App\Identity\Activity\UserAgentSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityTypeTest extends TestCase
{
    public function testOnlyRoutineEventsAreNotSensitive(): void
    {
        $routine = array_values(array_filter(
            ActivityType::all(),
            static fn(string $type): bool => !ActivityType::isSensitive($type),
        ));

        self::assertEqualsCanonicalizing([ActivityType::LOGIN, ActivityType::PASSWORD_RESET_REQUESTED], $routine);
    }

    public function testEveryTypeBelongsToAFilterGroupAndHasALabel(): void
    {
        foreach (ActivityType::all() as $type) {
            self::assertContains(ActivityType::group($type), ActivityType::GROUPS, $type);
            self::assertNotSame($type, ActivityType::label($type), $type . ' has no Spanish label');
        }
        self::assertSame('desconocido', ActivityType::label('desconocido'));
    }

    #[DataProvider('userAgents')]
    public function testUserAgentSummary(?string $userAgent, ?string $expected): void
    {
        self::assertSame($expected, UserAgentSummary::describe($userAgent));
    }

    /** @return iterable<string, array{string|null, string|null}> */
    public static function userAgents(): iterable
    {
        yield 'chrome on windows' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            'Chrome en Windows',
        ];
        yield 'edge, not chrome' => [
            'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0',
            'Edge en Windows',
        ];
        yield 'firefox on linux' => [
            'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Firefox en Linux',
        ];
        yield 'safari on mac' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Safari en macOS',
        ];
        yield 'chrome on android, not linux' => [
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36',
            'Chrome en Android',
        ];
        yield 'safari on iphone, not mac' => [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
            'Safari en iOS',
        ];
        yield 'only the system is known' => ['curl on Windows', 'Windows'];
        yield 'unrecognised' => ['curl/8.0', null];
        yield 'empty' => ['  ', null];
        yield 'missing' => [null, null];
    }
}
