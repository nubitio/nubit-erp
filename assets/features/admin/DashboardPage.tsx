import './DashboardPage.scss';

import { useMemo } from 'react';
import { DashboardPage as NubitDashboardPage } from '@nubitio/react-admin';

import { useSessionInfo } from '@shared/sessionInfo';
import { buildEfactDashboardConfig } from './dashboard/efactDashboardConfig';
import { useEfactDashboardData } from './dashboard/useEfactDashboardData';

export const DashboardPage = () => {
  const { currentBranchName } = useSessionInfo();
  const dashboardData = useEfactDashboardData();
  const dashboardConfig = useMemo(
    () => buildEfactDashboardConfig(currentBranchName || 'Panel'),
    [currentBranchName],
  );

  return (
    <NubitDashboardPage config={{ ...dashboardConfig, useData: () => dashboardData }} />
  );
};