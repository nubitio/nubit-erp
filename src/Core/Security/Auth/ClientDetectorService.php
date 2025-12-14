<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Symfony\Component\HttpFoundation\Request;

final readonly class ClientDetectorService
{
    private const string CLIENT_TYPE_HEADER = 'X-Client-Type';

    private const array MOBILE_CLIENT_TYPES = ['mobile', 'android', 'ios'];

    private const array MOBILE_USER_AGENT_KEYWORDS = ['mobile', 'android', 'iphone', 'ipad'];

    public function isMobileClient(Request $request): bool
    {
        $clientType = strtolower($request->headers->get(self::CLIENT_TYPE_HEADER, ''));

        if (in_array($clientType, self::MOBILE_CLIENT_TYPES, true)) {
            return true;
        }

        $userAgent = strtolower($request->headers->get('User-Agent', ''));

        return array_any(self::MOBILE_USER_AGENT_KEYWORDS, fn($keyword) => str_contains($userAgent, $keyword));
    }
}
