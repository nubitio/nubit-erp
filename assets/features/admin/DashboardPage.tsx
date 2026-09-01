import { useSessionInfo } from '@shared/sessionInfo';
import { useFeature, useTenantRuntimeConfig } from '@shared/hooks';
import { BriefingCard } from '@features/assistant/components/BriefingCard';
import { EfactDashboard } from './dashboard/EfactDashboard';
import { useEfactDashboardData } from './dashboard/useEfactDashboardData';

export const DashboardPage = () => {
  const { currentBranchName } = useSessionInfo();
  const { data: payload, loading, error, refetch } = useEfactDashboardData();
  const assistantEnabled = useFeature('ai_assistant');
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const { stats } = payload;

  return (
    <EfactDashboard
      title={currentBranchName ? `Resumen · ${currentBranchName}` : 'Resumen'}
      payload={payload}
      loading={loading}
      error={error}
      refetch={refetch}
      attention={{
        lowStockCount: stats.lowStockCount,
        pendingSunatCount: stats.pendingSunatCount,
      }}
      showGettingStarted={runtimeConfig ? !runtimeConfig.profile.onboardingCompleted : false}
      briefing={assistantEnabled ? <BriefingCard compact /> : null}
    />
  );
};
