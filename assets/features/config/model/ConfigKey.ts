/**
 * Canonical registry of all known Config keys — mirrors ConfigKey.php.
 *
 * Every key that drives behaviour in the frontend MUST be listed here.
 * Never use raw strings when reading/writing configs.
 */

// ── General ──────────────────────────────────────────────────────────────────
export const ConfigKey = {
  IGV:                       'app_option_igv',
  RC:                        'app_option_rc',
  PRINT_LOGO:                'app_option_print_logo',
  REQUIRE_CASH_REGISTER:     'app_option_require_cash_register',

  // Sales
  AUTO_INVOICE:              'app_option_auto_invoice',
  DEFAULT_CURRENCY:          'sales_default_currency',
  DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',

  // Inventory
  INVENTORY_NEGATIVE_STOCK_POLICY: 'inventory_negative_stock_policy',

  // Impresión
  PRINT_METHOD:              'print_method',

  // Technical migration keys (not shown in normal UI)
  SALES_V2_MODE:                         'sales_v2_mode',
  SALES_V2_DUAL_WRITE_ENABLED:           'app_option_sales_v2_dual_write_enabled',
  SALES_V2_DUAL_WRITE_STRICT:            'app_option_sales_v2_dual_write_strict',
  SALES_V2_CUTOVER_DATE:                 'sales_v2_cutover_date',
  SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT: 'sales_v2_disable_legacy_cash_movement',
  SALES_V2_DISABLE_LEGACY_STOCK_PROJ:    'sales_v2_disable_legacy_stock_projection',
  PURCHASES_V2_MODE:                     'purchases_v2_mode',
  PURCHASES_V2_DUAL_WRITE_ENABLED:       'app_option_purchases_v2_dual_write_enabled',
  PURCHASES_V2_DUAL_WRITE_STRICT:        'app_option_purchases_v2_dual_write_strict',
} as const;

export type ConfigKeyValue = (typeof ConfigKey)[keyof typeof ConfigKey];

export interface ConfigDto {
  id: number;
  name: ConfigKeyValue | string;
  value: string;
  type: 'string' | 'int' | 'float' | 'bool' | 'json';
}

/** Keys that are internal migration config — hidden from normal UI */
export const TECHNICAL_KEYS = new Set<string>([
  ConfigKey.SALES_V2_MODE,
  ConfigKey.SALES_V2_DUAL_WRITE_ENABLED,
  ConfigKey.SALES_V2_DUAL_WRITE_STRICT,
  ConfigKey.SALES_V2_CUTOVER_DATE,
  ConfigKey.SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT,
  ConfigKey.SALES_V2_DISABLE_LEGACY_STOCK_PROJ,
  ConfigKey.PURCHASES_V2_MODE,
  ConfigKey.PURCHASES_V2_DUAL_WRITE_ENABLED,
  ConfigKey.PURCHASES_V2_DUAL_WRITE_STRICT,
]);

export function isFeatureFlag(_key: string): boolean {
  return false; // No feature flags — print method is now a proper enum setting
}

export function isTechnical(key: string): boolean {
  return TECHNICAL_KEYS.has(key);
}

export function isBoolValue(value: string): boolean {
  return value === 'true' || value === 'false';
}
