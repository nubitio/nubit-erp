<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

enum CertificateStatus: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Superseded = 'superseded';
}
