<?php

namespace App\Consult\Domain;

enum DocType: string
{
    case DNI = 'dni';
    case RUC = 'ruc';
}