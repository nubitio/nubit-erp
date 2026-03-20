import React from 'react';
import { HashRouter as Router } from 'react-router-dom';

import { ThemeContext, useThemeContext } from '../theme/theme';
import { AuthProvider } from '@app/contexts/auth';
import { NavigationProvider } from '@app/contexts/navigation';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider } from '@core/mercure';

interface AppProvidersProps {
  children: React.ReactNode;
  themeContext: ReturnType<typeof useThemeContext>;
  mercureHubUrl: string | null;
}

export const AppProviders = ({ children, themeContext, mercureHubUrl }: AppProvidersProps) => {
  return (
    <Router>
      <ThemeContext.Provider value={themeContext}>
        <AuthProvider>
          <ConnectivityProvider>
            <OutboxSyncProvider>
              <MercureProvider hubUrl={mercureHubUrl}>
                <NavigationProvider>{children}</NavigationProvider>
              </MercureProvider>
            </OutboxSyncProvider>
          </ConnectivityProvider>
        </AuthProvider>
      </ThemeContext.Provider>
    </Router>
  );
};
