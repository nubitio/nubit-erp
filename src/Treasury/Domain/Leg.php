<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** A transfer writes two legs; every other source writes one `main` leg. */
enum Leg: string
{
    case Main = 'main';
    case In = 'in';
    case Out = 'out';
}
