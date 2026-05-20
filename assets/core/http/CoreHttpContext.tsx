import React, { createContext, useContext, useMemo } from 'react';
import { CoreHttpClient, createCoreHttpClient, type CoreHttpClientConfig } from './CoreHttpClient';

const defaultHttpClient = createCoreHttpClient();
const CoreHttpContext = createContext<CoreHttpClient>(defaultHttpClient);

export interface CoreHttpProviderProps {
  children: React.ReactNode;
  client?: CoreHttpClient;
  config?: CoreHttpClientConfig;
}

export function CoreHttpProvider({ children, client, config }: CoreHttpProviderProps) {
  const value = useMemo(
    () => client ?? createCoreHttpClient(config),
    [client, config],
  );

  return <CoreHttpContext.Provider value={value}>{children}</CoreHttpContext.Provider>;
}

export function useCoreHttpClient(): CoreHttpClient {
  return useContext(CoreHttpContext);
}
