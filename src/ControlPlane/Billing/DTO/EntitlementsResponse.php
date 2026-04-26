<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\DTO;

/**
 * DTO for the GET /api/tenant/entitlements response.
 *
 * When $debugMode is false (default), features are serialized as a flat map:
 *   "excel_export": true
 *
 * When $debugMode is true (X-Debug-Entitlements: 1 + ROLE_ADMIN), each feature
 * includes its resolution source and config:
 *   "excel_export": { "enabled": true, "source": "plan"|"override", "config": {} }
 */
final class EntitlementsResponse
{
    /**
     * @param string                                                                    $plan
     * @param array<string, array{limit: int, current: int}>                           $limits
     * @param array<string, array{enabled: bool, source: string, config: array<string, mixed>}> $features
     * @param bool                                                                      $debugMode
     */
    public function __construct(
        public readonly string $plan,
        public readonly array $limits,
        public readonly array $features,
        public readonly bool $debugMode = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $featuresPayload = [];

        foreach ($this->features as $key => $feature) {
            if ($this->debugMode) {
                $featuresPayload[$key] = [
                    'enabled' => $feature['enabled'],
                    'source'  => $feature['source'],
                    'config'  => $feature['config'],
                ];
            } else {
                $featuresPayload[$key] = $feature['enabled'];
            }
        }

        return [
            'plan'     => $this->plan,
            'limits'   => $this->limits,
            'features' => $featuresPayload,
        ];
    }
}
