import React from 'react';

export interface CoreConfig {
  locale: string;
  timezone: string;
}

// Module-level defaults — readable by non-React code (DateUtils, serializeFormData).
// Updated by CoreConfigProvider on mount/change, or directly via configureCoreDate().
const _coreConfig: CoreConfig = {
  locale: 'es',
  timezone: 'UTC',
};

export function configureCoreDate(config: Partial<CoreConfig>): void {
  if (config.locale !== undefined) _coreConfig.locale = config.locale;
  if (config.timezone !== undefined) _coreConfig.timezone = config.timezone;
}

export function getCoreLocale(): string {
  return _coreConfig.locale;
}

export function getCoreTimezone(): string {
  return _coreConfig.timezone;
}

const CoreConfigContext = React.createContext<CoreConfig>(_coreConfig);

export interface CoreConfigProviderProps {
  locale: string;
  timezone: string;
  children: React.ReactNode;
}

export const CoreConfigProvider = ({ locale, timezone, children }: CoreConfigProviderProps) => {
  // Keep module-level config in sync so static methods (DateUtils) stay consistent.
  configureCoreDate({ locale, timezone });

  const value = React.useMemo(() => ({ locale, timezone }), [locale, timezone]);

  return (
    <CoreConfigContext.Provider value={value}>
      {children}
    </CoreConfigContext.Provider>
  );
};

export function useCoreConfig(): CoreConfig {
  return React.useContext(CoreConfigContext);
}
