import React, { useMemo } from 'react';
import { HashRouter as Router } from 'react-router-dom';

import { ThemeProvider, DensityProvider, useAccentColor } from '@nubit/ui';
import { AuthProvider } from '@app/contexts/auth';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider, CoreConfigProvider, CoreProvider } from '@nubit/core';
// Single source of truth for this efact deployment's API base URL.
// Everything else in the app should go through getCoreApiBaseUrl() from @nubit/core.
const EFECT_API_BASE_URL = '/api/';
const EFACT_BUSINESS_LOCALE = 'es-PE';
const EFACT_BUSINESS_TIMEZONE = 'America/Lima';
import toast from '@shared/lib/SharedUtils';
import { SmartCrudAuthBridge } from './SmartCrudAuthBridge';
import { handleSessionExpired } from '@shared/lib/sessionExpiredEvent';

// Mounts inside DensityProvider; applies the persisted accent color on every
// page load — even when the SettingsPanel has never been opened.
const AccentColorApplier = () => {
  useAccentColor();
  return null;
};

interface AppProvidersProps {
  children: React.ReactNode;
  mercureHubUrl: string | null;
}

export const AppProviders = ({ children, mercureHubUrl }: AppProvidersProps) => {
  const httpConfig = useMemo(() => ({
    // baseUrl is now optional — it falls back to the one configured in CoreConfigProvider.
    // We still pass it here for explicitness / backward compat with advanced use cases.
    baseUrl: EFECT_API_BASE_URL,
    refreshPath: 'auth/refresh',
    loginPath: 'auth/login',
    onUnauthorized: handleSessionExpired,
  }), []);

  const runtimeConfig = useMemo(() => ({
    notify: toast,
    confirm: (message: string) => window.confirm(message),
  }), []);

  return (
    <ThemeProvider storageKey="app-theme" themePrefix="app-theme-" basePath="/themes/">
      <DensityProvider>
        <AccentColorApplier />
        <Router>
        <CoreProvider
          http={httpConfig}
          runtime={runtimeConfig}
        >
          <CoreConfigProvider
            locale={EFACT_BUSINESS_LOCALE}
            timezone={EFACT_BUSINESS_TIMEZONE}
            apiBaseUrl={EFECT_API_BASE_URL}
          >
            <AuthProvider>
              <SmartCrudAuthBridge>
                <ConnectivityProvider>
                  <OutboxSyncProvider>
                    <MercureProvider hubUrl={mercureHubUrl}>
                      {children}
                    </MercureProvider>
                  </OutboxSyncProvider>
                </ConnectivityProvider>
              </SmartCrudAuthBridge>
            </AuthProvider>
          </CoreConfigProvider>
        </CoreProvider>
        </Router>
      </DensityProvider>
    </ThemeProvider>
  );
};
