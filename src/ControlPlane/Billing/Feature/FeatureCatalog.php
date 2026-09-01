<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Feature;

/**
 * Code-defined catalogue of plan entitlements — the single source of truth for
 * feature keys, mirroring {@see \App\Identity\Security\PermissionCatalog}.
 *
 * Every gated entitlement lives here: its key, human label, category
 * (commercial add-on vs. ERP module), whether it carries a config payload, and
 * its per-plan defaults. Adding a feature here + running `app:features:sync`
 * populates `plan_features` — no per-feature migration needed. A compiler pass
 * ({@see Compiler\RequireFeatureKeyPass}) fails the build if a
 * `#[RequiresFeature]` / `hasFeature()` / `IS_GRANTED('FEATURE_*')` names a key
 * that is not in this catalogue.
 *
 * This is the *entitlement* axis only (what the plan allows). Vertical
 * capabilities are a separate mechanism — see ADR-013.
 */
final class FeatureCatalog
{
    private const string CAT_COMMERCIAL = 'commercial';
    private const string CAT_MODULE = 'module';

    /**
     * @return array<string, array{
     *     label: string,
     *     category: string,
     *     hasConfig: bool,
     *     defaultEnabledPlans: list<string>,
     *     defaultConfig: array<string, array<string, mixed>>,
     * }>
     */
    public static function all(): array
    {
        return [
            // ── Comercial ────────────────────────────────────────────────────
            'excel_export' => self::boolFeature('Exportación a Excel', self::CAT_COMMERCIAL, ['basic', 'pro']),
            'pdf_bulk_download' => self::boolFeature('Descarga masiva de PDF', self::CAT_COMMERCIAL, ['basic', 'pro']),
            'api_access' => self::boolFeature('Acceso API', self::CAT_COMMERCIAL, ['pro']),
            'multi_warehouse' => self::boolFeature('Multi-almacén', self::CAT_COMMERCIAL, ['pro']),
            'lot_tracking' => self::boolFeature('Control de lotes', self::CAT_COMMERCIAL, ['pro']),
            'custom_branding' => self::boolFeature('Marca personalizada', self::CAT_COMMERCIAL, ['pro']),
            'advanced_reports' => self::boolFeature('Reportes avanzados', self::CAT_COMMERCIAL, ['basic', 'pro']),
            'backup_download' => self::boolFeature('Descarga de respaldos', self::CAT_COMMERCIAL, ['basic', 'pro']),
            'sunat_auto_retry' => self::boolFeature('Reintento automático SUNAT', self::CAT_COMMERCIAL, ['basic', 'pro']),
            'webhooks' => self::boolFeature('Webhooks', self::CAT_COMMERCIAL, ['pro']),
            'priority_support' => self::boolFeature('Soporte prioritario', self::CAT_COMMERCIAL, ['pro']),
            'ai_assistant' => [
                'label' => 'Asistente IA',
                'category' => self::CAT_COMMERCIAL,
                'hasConfig' => true,
                'defaultEnabledPlans' => ['basic', 'pro'],
                'defaultConfig' => [
                    'free' => ['monthlyTokenCap' => 0, 'modelTier' => 'chat'],
                    'basic' => ['monthlyTokenCap' => 200_000, 'modelTier' => 'chat'],
                    'pro' => ['monthlyTokenCap' => 1_000_000, 'modelTier' => 'chat'],
                ],
            ],
            'api_rate_limit' => [
                'label' => 'Límite de tasa API',
                'category' => self::CAT_COMMERCIAL,
                'hasConfig' => true,
                'defaultEnabledPlans' => ['pro'],
                'defaultConfig' => ['pro' => ['requests_per_hour' => 1000]],
            ],
            'retention_days' => [
                'label' => 'Días de retención de datos',
                'category' => self::CAT_COMMERCIAL,
                'hasConfig' => true,
                'defaultEnabledPlans' => ['free', 'basic', 'pro'],
                'defaultConfig' => [
                    'free' => ['days' => 90],
                    'basic' => ['days' => 365],
                    'pro' => ['days' => -1],
                ],
            ],
            'concurrent_sessions' => [
                'label' => 'Sesiones concurrentes',
                'category' => self::CAT_COMMERCIAL,
                'hasConfig' => true,
                'defaultEnabledPlans' => ['free', 'basic', 'pro'],
                'defaultConfig' => [
                    'free' => ['max' => 1],
                    'basic' => ['max' => 3],
                    'pro' => ['max' => 10],
                ],
            ],

            // ── Módulos ERP ──────────────────────────────────────────────────
            'payroll' => self::boolFeature('Planilla', self::CAT_MODULE, ['basic', 'pro']),
            'general_ledger' => self::boolFeature('Contabilidad (libro mayor)', self::CAT_MODULE, ['basic', 'pro']),
            'bank_reconciliation' => self::boolFeature('Conciliación bancaria', self::CAT_MODULE, ['basic', 'pro']),
            'fixed_assets' => self::boolFeature('Activos fijos', self::CAT_MODULE, ['basic', 'pro']),
            'expense_reports' => self::boolFeature('Gastos de personal', self::CAT_MODULE, ['basic', 'pro']),
            'recurring_billing' => self::boolFeature('Facturación recurrente', self::CAT_MODULE, ['basic', 'pro']),
        ];
    }

    /**
     * @return array<string, string> category key => display label
     */
    public static function categories(): array
    {
        return [
            self::CAT_COMMERCIAL => 'Comercial',
            self::CAT_MODULE => 'Módulos ERP',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return list<string>
     */
    public static function configKeys(): array
    {
        return array_keys(array_filter(self::all(), static fn (array $f): bool => $f['hasConfig']));
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * @return array{label: string, category: string, hasConfig: bool, defaultEnabledPlans: list<string>, defaultConfig: array<string, array<string, mixed>>}|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Flat, UI-facing shape.
     *
     * @return list<array{key: string, label: string, category: string, hasConfig: bool}>
     */
    public static function list(): array
    {
        $out = [];
        foreach (self::all() as $key => $f) {
            $out[] = [
                'key' => $key,
                'label' => $f['label'],
                'category' => $f['category'],
                'hasConfig' => $f['hasConfig'],
            ];
        }

        return $out;
    }

    /**
     * The catalogue default for a (feature, plan) pair. Unknown feature or a
     * plan not in `defaultEnabledPlans` → disabled, no config.
     *
     * @return array{enabled: bool, config: array<string, mixed>|null}
     */
    public static function planDefault(string $key, string $plan): array
    {
        $feature = self::get($key);
        if ($feature === null) {
            return ['enabled' => false, 'config' => null];
        }

        return [
            'enabled' => in_array($plan, $feature['defaultEnabledPlans'], strict: true),
            'config' => $feature['defaultConfig'][$plan] ?? null,
        ];
    }

    /**
     * @param list<string> $enabledPlans
     *
     * @return array{label: string, category: string, hasConfig: bool, defaultEnabledPlans: list<string>, defaultConfig: array<string, array<string, mixed>>}
     */
    private static function boolFeature(string $label, string $category, array $enabledPlans): array
    {
        return [
            'label' => $label,
            'category' => $category,
            'hasConfig' => false,
            'defaultEnabledPlans' => $enabledPlans,
            'defaultConfig' => [],
        ];
    }
}
