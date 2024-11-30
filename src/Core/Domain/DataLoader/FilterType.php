<?php

namespace App\Core\Domain\DataLoader;

enum FilterType: string
{
    case AND = 'and';
    case OR = 'or';
}
