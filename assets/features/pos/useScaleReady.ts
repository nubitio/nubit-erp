import { useCallback, useEffect, useState } from 'react';
import { fetchScaleStatus } from '@features/sales/lib/localAgentClient';
import { subscribeToHardwareConfigChanges } from './hardwareAgentEvents';

export const useScaleReady = () => {
  const [scaleReady, setScaleReady] = useState(false);

  const refreshScaleReady = useCallback(async () => {
    try {
      const status = await fetchScaleStatus();
      setScaleReady(status.enabled && status.connected);
    } catch {
      setScaleReady(false);
    }
  }, []);

  useEffect(() => {
    queueMicrotask(() => void refreshScaleReady());
    return subscribeToHardwareConfigChanges(() => void refreshScaleReady());
  }, [refreshScaleReady]);

  return scaleReady;
};
