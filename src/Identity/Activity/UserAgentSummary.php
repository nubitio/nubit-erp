<?php

declare(strict_types=1);

namespace App\Identity\Activity;

/** "Chrome en Windows" from a User-Agent. Deliberately coarse: it is a hint for a human, not a fingerprint. */
final class UserAgentSummary
{
    public static function describe(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        // Order matters: Edge and Opera also say Chrome; Chrome also says Safari.
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/'), str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };
        // Android and iOS UAs also contain Linux / "like Mac OS X": check them first.
        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X'), str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $system !== null => $browser . ' en ' . $system,
            $browser !== null => $browser,
            $system !== null => $system,
            default => null,
        };
    }
}
