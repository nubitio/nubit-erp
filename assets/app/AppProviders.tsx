import React from 'react';
import { HashRouter as Router } from 'react-router-dom';

import { ThemeContext, useThemeContext } from '../theme/theme';
import { AuthProvider } from '@app/contexts/auth';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider } from '@core/mercure';
import { Constants } from '@core/common/Constants';
import { CoreProvider } from '@nubit/core';
import toast from '@shared/lib/SharedUtils';
import { SmartCrudAuthBridge } from './SmartCrudAuthBridge';

interface AppProvidersProps {
  children: React.ReactNode;
  themeContext: ReturnType<typeof useThemeContext>;
  mercureHubUrl: string | null;
}

export const AppProviders = ({ children, themeContext, mercureHubUrl }: AppProvidersProps) => {
  return (
    <Router>
      <CoreProvider
        http={{
          baseUrl: Constants.API_BASE_URL,
          refreshPath: 'auth/refresh',
          loginPath: 'auth/login',
        }}
        runtime={{
          notify: toast,
          confirm: (message) => window.confirm(message),
        }}
      >
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
      </CoreProvider>
    </Router>
  );
};
