<?php

declare(strict_types=1);

namespace App\Settings\Enum;

/**
 * Where a `tenant_capabilities` row came from (NB-5). Ordered loosely by
 * authority: a `manual` or `support` override should win over a `preset` default
 * when the resolver merges them (NB-6).
 */
enum CapabilitySource: string
{
    case Preset = 'preset';
    case Manual = 'manual';
    case Migration = 'migration';
    case Support = 'support';
}
