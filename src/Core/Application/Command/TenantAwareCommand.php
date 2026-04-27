<?php

declare(strict_types=1);

namespace App\Core\Application\Command;

use Nubit\Platform\Symfony\Console\TenantAwareCommand as PlatformTenantAwareCommand;

abstract class TenantAwareCommand extends PlatformTenantAwareCommand
{
}
