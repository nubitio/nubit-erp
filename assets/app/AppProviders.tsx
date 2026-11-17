import React, { useMemo } from 'react';
import { RouterRoot } from './RouterRoot';

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
import { httpTransport } from '@shared/api/httpTransport';
import { SmartCrudAuthBridge } from './SmartCrudAuthBridge';
import { handleSessionExpired } from '@shared/lib/sessionExpiredEvent';

function notifyServerError(error: unknown) {
  const status =
    typeof error === 'object' && error !== null && 'status' in error
      ? Number(error.status)
      : undefined;

  if (status !== undefined && status >= 500) {
    toast('Error interno del servidor. No se pudo completar la operación.', 'error', 6000);
  }
}

// Apply persisted choices only; otherwise preserve the Nubit theme's own accent.
const AccentColorApplier = () => {
  useAccentColor();
  return null;
};

interface AppProvidersProps {
  children: React.ReactNode;
  mercureHubUrl: string | null;
}

export const AppProviders = ({ children, mercureHubUrl }: AppProvidersProps) => {
  const httpConfig = useMemo(
    () => ({
      // baseUrl is now optional — it falls back to the one configured in CoreConfigProvider.
      // We still pass it here for explicitness / backward compat with advanced use cases.
      baseUrl: EFECT_API_BASE_URL,
      // Bearer token, CSRF, correlation id and refresh/login paths are shared with
      // the module-level `httpClient` — see httpTransport.ts. Only the failure UX
      // differs here: crud surfaces errors through its own grid/form UI, so this
      // client keeps the narrower "server error" toast.
      ...httpTransport,
      onUnauthorized: handleSessionExpired,
      onError: notifyServerError,
    }),
    [],
  );

  const runtimeConfig = useMemo(
    () => ({
      notify: toast,
      confirm: (message: string) => window.confirm(message),
    }),
    [],
  );

  return (
    <ThemeProvider
      storageKey="app-theme"
      themePrefix="app-theme-"
      basePath="/themes/"
      visualStyle="nubit"
    >
      <DensityProvider>
        <AccentColorApplier />
        <RouterRoot>
          <CoreProvider http={httpConfig} runtime={runtimeConfig}>
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
                      <MercureProvider hubUrl={mercureHubUrl}>{children}</MercureProvider>
                    </OutboxSyncProvider>
                  </ConnectivityProvider>
                </SmartCrudAuthBridge>
              </AuthProvider>
            </CoreConfigProvider>
          </CoreProvider>
        </RouterRoot>
      </DensityProvider>
    </ThemeProvider>
  );
};
