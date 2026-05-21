import React, { useMemo } from 'react';
import { HashRouter as Router } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

import { ThemeProvider } from '@nubit/ui';
import { AuthProvider } from '@app/contexts/auth';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider, CoreConfigProvider, CoreProvider } from '@nubit/core';
import { API_BASE_URL } from '@app/constants';
import toast from '@shared/lib/SharedUtils';
import { SmartCrudAuthBridge } from './SmartCrudAuthBridge';

interface AppProvidersProps {
  children: React.ReactNode;
  mercureHubUrl: string | null;
}

export const AppProviders = ({ children, mercureHubUrl }: AppProvidersProps) => {
  const { i18n } = useTranslation();
  const locale = i18n.language?.split('-')[0] || 'es';

  const httpConfig = useMemo(() => ({
    baseUrl: API_BASE_URL,
    refreshPath: 'auth/refresh',
    loginPath: 'auth/login',
  }), []);

  const runtimeConfig = useMemo(() => ({
    notify: toast,
    confirm: (message: string) => window.confirm(message),
  }), []);

  return (
    <ThemeProvider storageKey="app-theme" themePrefix="app-theme-" basePath="/themes/">
      <Router>
        <CoreProvider
          http={httpConfig}
          runtime={runtimeConfig}
        >
          <CoreConfigProvider locale={locale} timezone="America/Lima">
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
    </ThemeProvider>
  );
};
