<?php

declare(strict_types=1);

namespace App\ControlPlane\Certificate;

interface CertificateAlertServiceInterface
{
    public function sendExpiringAlerts(int $days = 30): int;
}
