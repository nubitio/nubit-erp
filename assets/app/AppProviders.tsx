import React from 'react';
import { HashRouter as Router } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

import { ThemeContext, useThemeContext } from '../theme/theme';
import { AuthProvider } from '@app/contexts/auth';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider, CoreConfigProvider } from '@nubit/core';
import { API_BASE_URL } from '@app/constants';
import { CoreProvider } from '@nubit/core';
import toast from '@shared/lib/SharedUtils';
import { SmartCrudAuthBridge } from './SmartCrudAuthBridge';

interface AppProvidersProps {
  children: React.ReactNode;
  themeContext: ReturnType<typeof useThemeContext>;
  mercureHubUrl: string | null;
}

export const AppProviders = ({ children, themeContext, mercureHubUrl }: AppProvidersProps) => {
  const { i18n } = useTranslation();
  const locale = i18n.language?.split('-')[0] || 'es';

  return (
    <Router>
      <CoreProvider
        http={{
          baseUrl: API_BASE_URL,
          refreshPath: 'auth/refresh',
          loginPath: 'auth/login',
        }}
        runtime={{
          notify: toast,
          confirm: (message) => window.confirm(message),
        }}
      >
        <CoreConfigProvider locale={locale} timezone="America/Lima">
          <ThemeContext.Provider value={themeContext}>
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
          </ThemeContext.Provider>
        </CoreConfigProvider>
      </CoreProvider>
    </Router>
  );
};
