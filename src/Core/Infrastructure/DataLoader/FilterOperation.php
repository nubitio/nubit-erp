<?php

namespace App\Core\Infrastructure\DataLoader;

enum FilterOperation: string
{
    case Contains = 'contains';
    case Equals = 'equal';
    case NotEquals = 'notequal';
    case BeginsWith = 'beginwith';
    case EndsWith = 'endwith';
    case LessThan = 'less';
    case LessThanOrEqual = 'lessorequal';
    case GreaterThan = 'greater';
    case GreaterThanOrEqual = 'greaterorequal';
    case In = 'in';
    case NotIn = 'notin';
    case IsNull = 'isnull';
    case IsNotNull = 'isnotnull';
}