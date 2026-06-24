import React, { useEffect, useMemo } from 'react';
import { HashRouter as Router } from 'react-router-dom';

import { ThemeProvider, DensityProvider, useAccentColor } from '@nubitio/ui';
import { AuthProvider } from '@app/contexts/auth';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider, CoreConfigProvider, CoreProvider } from '@nubitio/core';
// Single source of truth for this efact deployment's API base URL.
// Everything else in the app should go through getCoreApiBaseUrl() from @nubitio/core.
const EFECT_API_BASE_URL = '/api/';
const EFACT_BUSINESS_LOCALE = 'es-PE';
const EFACT_BUSINESS_TIMEZONE = 'America/Lima';
const EFACT_BUSINESS_CURRENCY = 'PEN';
import toast from '@shared/lib/SharedUtils';
import { SmartCrudAuthBridge } from './SmartCrudAuthBridge';
import { handleSessionExpired } from '@shared/lib/sessionExpiredEvent';

function notifyServerError(error: unknown) {
  const status = typeof error === 'object' && error !== null && 'status' in error
    ? Number(error.status)
    : undefined;

  if (status !== undefined && status >= 500) {
    toast('Error interno del servidor. No se pudo completar la operación.', 'error', 6000);
  }
}

// Applies the persisted accent (or eFact azure default) on every page load.
const AccentColorApplier = () => {
  const { accent } = useAccentColor();

  useEffect(() => {
    document.documentElement.style.setProperty('--accent-color', accent);
  }, [accent]);

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
    onError: notifyServerError,
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
            currency={EFACT_BUSINESS_CURRENCY}
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
