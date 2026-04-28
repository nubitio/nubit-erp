<?php

declare(strict_types=1);

namespace App\EInvoicing\ApiResource;

enum ConsultDocType: string
{
    case DNI = 'dni';
    case RUC = 'ruc';
}
