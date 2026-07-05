import React, { useEffect, useState } from 'react';

import { useLocation } from 'react-router-dom';

import { hasCommittedAuthSession, useAuth } from '@app/contexts/auth';
import { useScreenSizeClass } from '@nubitio/admin';
import { useTheme } from '@nubitio/ui';
import { HydraResourceSchemaProvider, HydraResourceStoreProvider, SchemaProvider } from '@nubitio/hydra';
import { Content } from './Content';

import '../../styles.scss';
import '../../theme/theme';
import './custom.scss';
import { UnauthenticatedContent } from './UnauthenticatedContent';
import { FullScreenContent } from './FullScreenContent';
import { MercureManager } from '@nubitio/core';
import { AppProviders } from '../AppProviders';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { TenantOnboardingGate } from '@features/onboarding/TenantOnboardingGate';

const AppLoading = () => (
  <div className="app-loading" role="status" aria-label="Cargando">
    <span className="app-loading__spinner" aria-hidden="true" />
  </div>
);

export function RootApp() {
  const { user, loading } = useAuth();
  const hasCommittedSession = hasCommittedAuthSession();

  if (loading || (!user && hasCommittedSession)) {
    return <AppLoading />;
  }

  if (user) {
    return <AuthenticatedRoot />;
  }

  return <UnauthenticatedContent />;
}

function AuthenticatedRoot() {
  const isFullScreen = ['pos'].includes(useLocation().pathname.substring(1));
  const runtimeConfig = useTenantRuntimeConfig();

  if (runtimeConfig.isLoading) {
    return <AppLoading />;
  }

  if (runtimeConfig.data?.profile.onboardingCompleted === false) {
    return <TenantOnboardingGate />;
  }

  const page = isFullScreen ? <FullScreenContent /> : <Content />;

  return (
    <SchemaProvider>
      <HydraResourceSchemaProvider>
        <HydraResourceStoreProvider>
          {page}
        </HydraResourceStoreProvider>
      </HydraResourceSchemaProvider>
    </SchemaProvider>
  );
}

function AppShell({ screenSizeClass }: { screenSizeClass: string }) {
  const { isLoaded } = useTheme();

  return (
    <div className={`app ${screenSizeClass}`}>
      {isLoaded ? (
        <RootApp />
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
