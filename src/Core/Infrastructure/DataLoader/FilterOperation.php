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

    public static function fromString(string $value): self
    {
        return match ($value) {
            'contains' => self::Contains,
            'equal', '=' => self::Equals,
            'notequal' => self::NotEquals,
            'beginwith' => self::BeginsWith,
            'endwith' => self::EndsWith,
            '<' => self::LessThan,
            '<=' => self::LessThanOrEqual,
            '>' => self::GreaterThan,
            '>=' => self::GreaterThanOrEqual,
            'in' => self::In,
            'notin' => self::NotIn,
            'isnull' => self::IsNull,
            'isnotnull' => self::IsNotNull,
            default => throw new \InvalidArgumentException("Invalid filter operation: $value"),
        };
    }
}
