<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Auth\AuthUserDto;
use App\Entity\Product;

/**
 * Resolves the negative stock policy and determines whether a user can sell below zero.
 */
final readonly class NegativeStockPolicyResolver
{
    public const string BLOCK = 'BLOCK';
    public const string ALLOW_WITH_ROLE = 'ALLOW_WITH_ROLE';
    public const string ALLOW_ALL = 'ALLOW_ALL';

    public function __construct(
        private SettingsService $settings,
    ) {
    }

    public function resolvePolicy(): string
    {
        $raw = strtoupper(trim((string) $this->settings->get(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '')));

        return match ($raw) {
            'STRICT' => self::BLOCK,
            'WARNING_ONLY' => self::ALLOW_WITH_ROLE,
            'ALLOW_NEGATIVE' => self::ALLOW_ALL,
            self::BLOCK, self::ALLOW_WITH_ROLE, self::ALLOW_ALL => $raw,
            default => self::BLOCK,
        };
    }

    public function canSell(string $policy, AuthUserDto $user): bool
    {
        if ($policy === self::ALLOW_ALL) {
            return true;
        }

        if ($policy !== self::ALLOW_WITH_ROLE) {
            return false;
        }

        $roles = $user->roles ?? [];

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }

    public function shouldUseCoreValidation(): bool
    {
        $mode = strtolower(trim((string) $this->settings->get(Constants::INVENTORY_CORE_MODE, 'legacy')));
        if (in_array($mode, ['dual_write', 'v2_authoritative'], true)) {
            return true;
        }

        return filter_var(
            $this->settings->get(Constants::INVENTORY_CORE_DUAL_WRITE, 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function isStockTracked(Product $product): bool
    {
        return $product->getTrackInventory();
    }
}
