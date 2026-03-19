import React, { Suspense, useEffect, useState } from 'react';

import { HashRouter as Router, useLocation } from 'react-router-dom';

import LoadPanel from 'devextreme-react/load-panel';

import { NavigationProvider } from './contexts/navigation';
import { AuthProvider, useAuth } from './contexts/auth';
import { useScreenSizeClass } from './utils/media-query';
import { Content } from './Content';
import { ThemeContext, useThemeContext } from './theme/theme';
import { configureDevExtreme } from '@app/providers/devextreme';

import './styles.scss';
import './theme/theme';
import './styles/custom.scss';
import { UnauthenticatedContent } from './UnauthenticatedContent';
import { FullScreenContent } from './FullScreenContent';
import { ConnectivityProvider } from '@offline/connectivity/ConnectivityContext';
import { OutboxSyncProvider } from '@offline/sync/OutboxSyncContext';
import { MercureProvider, MercureManager } from '@core/mercure';

function RootApp() {
  const { user, loading } = useAuth();
  const isFullScreen = ['pos'].includes(useLocation().pathname.substring(1));

  if (loading) {
    return <LoadPanel visible />;
  }

  if (user) {
    if (isFullScreen) {
      return <FullScreenContent />;
    }

    return <Content />;
  }

  return <UnauthenticatedContent />;
}

export const EfactApp = () => {
  const screenSizeClass = useScreenSizeClass();
  const themeContext = useThemeContext();

  // Reactive hub URL: updated by MercureManager whenever the Axios interceptor
  // discovers the `Link: <url>; rel="mercure"` header in any API response.
  const [mercureHubUrl, setMercureHubUrl] = useState<string | null>(MercureManager.getHubUrl());

  useEffect(() => {
    configureDevExtreme();
  }, []);

  useEffect(() => {
    // Subscribe to hub URL changes discovered by the Axios response interceptor.
    // Returns an unsubscribe function used as the cleanup.
    return MercureManager.onHubUrlChange(setMercureHubUrl);
  }, []);

  return (
    <Router>
      <ThemeContext.Provider value={themeContext}>
        <AuthProvider>
          <ConnectivityProvider>
            <OutboxSyncProvider>
              <MercureProvider hubUrl={mercureHubUrl}>
                <NavigationProvider>
                  <div className={`app ${screenSizeClass}`}>
                    {themeContext.isLoaded ? (
                      <Suspense fallback={<LoadPanel visible />}>
                        <RootApp />
                      </Suspense>
                    ) : (
                      ''
                    )}
                  </div>
                </NavigationProvider>
              </MercureProvider>
            </OutboxSyncProvider>
          </ConnectivityProvider>
        </AuthProvider>
      </ThemeContext.Provider>
    </Router>
  );
};
