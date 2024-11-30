<?php

namespace App\Core\Domain\DataLoader;

enum JoinType: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
}
