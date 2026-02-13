<?php

declare(strict_types=1);

namespace App\Onboarding\Payment;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';
}
