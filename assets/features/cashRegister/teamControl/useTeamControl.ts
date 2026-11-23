import { useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import type { MemberEvent, TeamControlOverview } from './teamControlFormat';

const base = () => `${getCoreApiBaseUrl()}team-control`;

export interface TeamControlPeriod {
  from: string;
  to: string;
}

export function useTeamControl(period: TeamControlPeriod) {
  return useQuery({
    queryKey: ['team-control', 'overview', period],
    retry: false,
    queryFn: async () =>
      (
        await httpClient.get<TeamControlOverview>(`${base()}?from=${period.from}&to=${period.to}`, {
          errorPresentation: 'inline',
        })
      ).data,
  });
}

/** What one person did in the period. `userId` 0 is the records that predate author tracking. */
export function useMemberEvents(userId: number | null, period: TeamControlPeriod) {
  return useQuery({
    queryKey: ['team-control', 'member', userId, period],
    enabled: userId !== null,
    retry: false,
    queryFn: async () =>
      (
        await httpClient.get<{ events: MemberEvent[] }>(
          `${base()}/members/${userId}?from=${period.from}&to=${period.to}`,
          { errorPresentation: 'inline' },
        )
      ).data.events,
  });
}
