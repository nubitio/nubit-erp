import React, { Suspense, useEffect, useState } from 'react';

import { useLocation } from 'react-router-dom';

import { hasCommittedAuthSession, useAuth } from '@app/contexts/auth';
import { useScreenSizeClass } from '../../utils/media-query';
import { Content } from './Content';
import { useThemeContext } from '../../theme/theme';
import { configureUiAdapter } from '@app/providers/uiAdapter';

import '../../styles.scss';
import '../../theme/theme';
import './custom.scss';
import { UnauthenticatedContent } from './UnauthenticatedContent';
import { FullScreenContent } from './FullScreenContent';
import { MercureManager } from '@core/mercure';
import { AppProviders } from '../AppProviders';

const AppLoading = () => (
  <div className="app-loading" role="status" aria-label="Cargando">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

export function RootApp() {
  const { user, loading } = useAuth();
  const isFullScreen = ['pos'].includes(useLocation().pathname.substring(1));
  const hasCommittedSession = hasCommittedAuthSession();

  if (loading || (!user && hasCommittedSession)) {
    return <AppLoading />;
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
    configureUiAdapter();
  }, []);

  useEffect(() => {
    // Subscribe to hub URL changes discovered by the Axios response interceptor.
    // Returns an unsubscribe function used as the cleanup.
    return MercureManager.onHubUrlChange(setMercureHubUrl);
  }, []);

  return (
    <AppProviders themeContext={themeContext} mercureHubUrl={mercureHubUrl}>
      <div className={`app ${screenSizeClass}`}>
        {themeContext.isLoaded ? (
          <Suspense fallback={<AppLoading />}>
            <RootApp />
          </Suspense>
        ) : (
          ''
        )}
      </div>
    </AppProviders>
  );
};
