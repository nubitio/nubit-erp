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
  RECEIPT_REQUIRED:          'app_option_receipt_required',

  // Sales
  AUTO_INVOICE:              'app_option_auto_invoice',
  DEFAULT_CURRENCY:          'sales_default_currency',
  DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',

  // Inventory
  INVENTORY_NEGATIVE_STOCK_POLICY: 'inventory_negative_stock_policy',

  // Impresión
  PRINT_METHOD:              'print_method',

  // Technical migration keys (not shown in normal UI)
  SALES_V2_MODE:                         'sales_v2_mode',           // obsolete: locked to v2_authoritative by migration, never read
  SALES_V2_DUAL_WRITE_ENABLED:           'app_option_sales_v2_dual_write_enabled',
  SALES_V2_DUAL_WRITE_STRICT:            'app_option_sales_v2_dual_write_strict',
  SALES_V2_CUTOVER_DATE:                 'sales_v2_cutover_date',
  SALES_V2_DISABLE_LEGACY_CASH_MOVEMENT: 'sales_v2_disable_legacy_cash_movement',
  SALES_V2_DISABLE_LEGACY_STOCK_PROJ:    'sales_v2_disable_legacy_stock_projection',
  PURCHASES_V2_MODE:                     'purchases_v2_mode',
  PURCHASES_V2_DUAL_WRITE_ENABLED:       'app_option_purchases_v2_dual_write_enabled',
  PURCHASES_V2_DUAL_WRITE_STRICT:        'app_option_purchases_v2_dual_write_strict',

  // Technical SUNAT retry/circuit-breaker keys
  SUNAT_SEND_ENABLED:             'app_option_sunat_send_enabled',
  SUNAT_REJECTION_STREAK:         'app_option_sunat_rejection_streak',
  SUNAT_REJECTION_THRESHOLD:      'app_option_sunat_rejection_threshold',
  SUNAT_SEND_RETRY_MAX_ATTEMPTS:  'app_option_sunat_send_retry_max_attempts',
  SUNAT_SEND_RETRY_BASE_SECONDS:  'app_option_sunat_send_retry_base_seconds',
  SUNAT_SEND_RETRY_MAX_SECONDS:   'app_option_sunat_send_retry_max_seconds',

  // Technical inventory migration keys
  INVENTORY_CORE_MODE:                    'inventory_core_mode',
  INVENTORY_CORE_DUAL_WRITE_ENABLED:      'inventory_core_dual_write_enabled',
  INVENTORY_CORE_READ_STOCK_ITEMS_ENABLED: 'inventory_core_read_stock_items_enabled', // write-only: seeded but never read

  // Technical timestamp
  SALES_USD_EXCHANGE_RATE_UPDATED_AT: 'sales_usd_exchange_rate_updated_at',
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
  ConfigKey.SUNAT_SEND_ENABLED,
  ConfigKey.SUNAT_REJECTION_STREAK,
  ConfigKey.SUNAT_REJECTION_THRESHOLD,
  ConfigKey.SUNAT_SEND_RETRY_MAX_ATTEMPTS,
  ConfigKey.SUNAT_SEND_RETRY_BASE_SECONDS,
  ConfigKey.SUNAT_SEND_RETRY_MAX_SECONDS,
  ConfigKey.INVENTORY_CORE_MODE,
  ConfigKey.INVENTORY_CORE_DUAL_WRITE_ENABLED,
  ConfigKey.INVENTORY_CORE_READ_STOCK_ITEMS_ENABLED,
  ConfigKey.SALES_USD_EXCHANGE_RATE_UPDATED_AT,
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
