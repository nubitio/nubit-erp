<?php

declare(strict_types=1);

namespace App\Efact\Domain\Finance;

/** In which order an amount settles open items (D-08: the default is the oldest due date). */
enum AllocationStrategy: string
{
    case OldestDue = 'oldest_due';
    case OldestIssued = 'oldest_issued';
}
