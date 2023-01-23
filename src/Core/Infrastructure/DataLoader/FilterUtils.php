<?php

namespace App\Core\Infrastructure\DataLoader;

class FilterUtils
{
    public static function getOperation(FilterOperation $op): string
    {
        return match ($op) {
            FilterOperation::Equals => '=',
            FilterOperation::NotEquals => '!=',
            FilterOperation::GreaterThan => '>',
            FilterOperation::GreaterThanOrEqual => '>=',
            FilterOperation::LessThan => '<',
            FilterOperation::LessThanOrEqual => '<=',
            FilterOperation::Contains, FilterOperation::BeginsWith, FilterOperation::EndsWith => 'LIKE',
            FilterOperation::In => 'IN',
            FilterOperation::NotIn => 'NOT IN',
            FilterOperation::IsNull => 'IS NULL',
            FilterOperation::IsNotNull => 'IS NOT NULL',
        };
    }
}