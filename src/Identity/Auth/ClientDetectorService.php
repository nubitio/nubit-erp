<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use Symfony\Component\HttpFoundation\Request;

final readonly class ClientDetectorService
{
    private const string CLIENT_TYPE_HEADER = 'X-Client-Type';

    private const array MOBILE_CLIENT_TYPES = ['android', 'ios'];

    public function isMobileClient(Request $request): bool
    {
        $clientType = strtolower($request->headers->get(self::CLIENT_TYPE_HEADER, ''));

        return $clientType !== '' && in_array($clientType, self::MOBILE_CLIENT_TYPES, true);
    }
}
