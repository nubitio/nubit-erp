import './DashboardPage.scss';

import { useMemo } from 'react';
import { DashboardPage as NubitDashboardPage } from '@nubitio/react-admin';

import { useSessionInfo } from '@shared/sessionInfo';
import { useFeature } from '@shared/hooks';
import { BriefingCard } from '@features/assistant/components/BriefingCard';
import { buildEfactDashboardConfig } from './dashboard/efactDashboardConfig';
import { useEfactDashboardData } from './dashboard/useEfactDashboardData';

export const DashboardPage = () => {
  const { currentBranchName } = useSessionInfo();
  const dashboardData = useEfactDashboardData();
  const dashboardConfig = useMemo(
    () => buildEfactDashboardConfig(currentBranchName ? `Resumen · ${currentBranchName}` : 'Resumen'),
    [currentBranchName],
  );
  const assistantEnabled = useFeature('ai_assistant');

  return (
    <>
      {assistantEnabled ? (
        <div style={{ padding: '12px 16px 0' }}>
          <BriefingCard compact />
        </div>
      ) : null}
      <NubitDashboardPage config={{ ...dashboardConfig, useData: () => dashboardData }} />
    </>
  );
};
