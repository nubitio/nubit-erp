<?php

declare(strict_types=1);

namespace App\Billing\ApiResource;

enum ConsultDocType: string
{
    case DNI = 'dni';
    case RUC = 'ruc';
}
