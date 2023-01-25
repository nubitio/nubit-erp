<?php

namespace App\Core\Infrastructure\DataLoader;

enum JoinType: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
}
