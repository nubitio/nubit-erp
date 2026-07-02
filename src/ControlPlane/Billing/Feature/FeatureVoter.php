<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature;

use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Security Voter that handles IS_GRANTED('FEATURE_*') expressions.
 *
 * Attribute format:  'FEATURE_<FEATURE_KEY_UPPERCASE>'
 * Example:           IS_GRANTED('FEATURE_EXCEL_EXPORT')
 *                    → checks featureKey 'excel_export' via FeatureCheckerInterface
 *
 * The voter returns ACCESS_GRANTED when the current tenant's plan (or an
 * active override) has the feature enabled, and ACCESS_DENIED otherwise.
 * It abstains for any attribute that does not start with the 'FEATURE_' prefix.
 *
 * @extends Voter<string, mixed>
 */
final class FeatureVoter extends Voter
{
    private const string PREFIX = 'FEATURE_';

    public function __construct(
        private readonly FeatureCheckerInterface $featureChecker,
    ) {}

    /**
     * This voter only handles attributes that start with 'FEATURE_'.
     * The $subject is intentionally ignored — feature access is tenant-scoped,
     * not object-scoped.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::PREFIX);
    }

    /**
     * Converts 'FEATURE_EXCEL_EXPORT' → 'excel_export' and delegates to
     * FeatureCheckerInterface::hasFeature().
     */
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null,
    ): bool {
        $featureKey = strtolower(substr($attribute, \strlen(self::PREFIX)));

        return $this->featureChecker->hasFeature($featureKey);
    }
}
