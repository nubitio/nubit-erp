import React, { Suspense, useEffect, useState } from 'react';

import { useLocation } from 'react-router-dom';

import { hasCommittedAuthSession, useAuth } from '@app/contexts/auth';
import { useScreenSizeClass } from '@nubit/admin';
import { useTheme } from '@nubit/ui';
import { Content } from './Content';

import '../../styles.scss';
import '../../theme/theme';
import './custom.scss';
import { UnauthenticatedContent } from './UnauthenticatedContent';
import { FullScreenContent } from './FullScreenContent';
import { MercureManager } from '@nubit/core';
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

function AppShell({ screenSizeClass }: { screenSizeClass: string }) {
  const { isLoaded } = useTheme();

  return (
    <div className={`app ${screenSizeClass}`}>
      {isLoaded ? (
        <Suspense fallback={<AppLoading />}>
          <RootApp />
        </Suspense>
      ) : (
        ''
      )}
    </div>
  );
}

export const EfactApp = () => {
  const screenSizeClass = useScreenSizeClass();
  const [mercureHubUrl, setMercureHubUrl] = useState<string | null>(MercureManager.getHubUrl());

  useEffect(() => {
    return MercureManager.onHubUrlChange(setMercureHubUrl);
  }, []);

  return (
    <AppProviders mercureHubUrl={mercureHubUrl}>
      <AppShell screenSizeClass={screenSizeClass} />
    </AppProviders>
  );
};
