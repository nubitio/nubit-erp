import React, { Suspense, useEffect } from 'react';

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

function RootApp() {
  const { user, loading } = useAuth();
  const isLoginForm = ['login', 'reset-password', 'create-account'].includes(
    useLocation().pathname.substring(1),
  );

  const isFullScreen = ['pos'].includes(useLocation().pathname.substring(1));

  if (loading) {
    return <LoadPanel visible />;
  }

  if (user && !isLoginForm && !isFullScreen) {
    return <Content />;
  }

  if (isFullScreen) {
    return <FullScreenContent />;
  }

  return <UnauthenticatedContent />;
}

export const EfactApp = () => {
  const screenSizeClass = useScreenSizeClass();
  const themeContext = useThemeContext();

  useEffect(() => {
    configureDevExtreme();
  }, []);

  return (
    <Router>
      <ThemeContext.Provider value={themeContext}>
        <AuthProvider>
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
        </AuthProvider>
      </ThemeContext.Provider>
    </Router>
  );
};
