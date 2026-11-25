<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** Transfers and settlements write two legs; single movements use the `main` leg. */
enum Leg: string
{
    case Main = 'main';
    case In = 'in';
    case Out = 'out';
}
