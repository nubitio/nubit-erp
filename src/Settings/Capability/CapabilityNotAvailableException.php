<?php

declare(strict_types=1);

namespace App\Settings\Capability;

use RuntimeException;

use function sprintf;

/**
 * Thrown by {@see TenantCapabilityResolver::require()} when a capability the
 * caller needs is not enabled for the current tenant.
 */
final class CapabilityNotAvailableException extends RuntimeException
{
    public function __construct(
        public readonly string $capabilityKey,
    ) {
        parent::__construct(sprintf('Capability "%s" is not available for this tenant.', $capabilityKey));
    }
}
