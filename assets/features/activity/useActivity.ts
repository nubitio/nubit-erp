import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { buildActivityQuery, type ActivityFilters, type ActivityItem } from './activityFormat';

interface ActivityPageData {
  items: ActivityItem[];
  total: number;
  page: number;
  limit: number;
}

export interface ActivitySummaryItem {
  type: string;
  label: string;
  count: number;
}

const base = () => `${getCoreApiBaseUrl()}activity-events`;

export function useActivityList(filters: ActivityFilters) {
  return useInfiniteQuery({
    queryKey: ['activity', 'list', filters],
    initialPageParam: 1,
    retry: false,
    queryFn: async ({ pageParam }) =>
      (
        await httpClient.get<ActivityPageData>(
          `${base()}?${buildActivityQuery(filters, pageParam)}`,
          {
            errorPresentation: 'inline',
          },
        )
      ).data,
    getNextPageParam: (last) => (last.page * last.limit < last.total ? last.page + 1 : undefined),
  });
}

export function useActivitySummary() {
  return useQuery({
    queryKey: ['activity', 'summary'],
    retry: false,
    queryFn: async () =>
      (
        await httpClient.get<{ days: number; items: ActivitySummaryItem[] }>(
          `${base()}/summary?days=7`,
          {
            errorPresentation: 'inline',
          },
        )
      ).data,
  });
}

export function useActivityActors() {
  return useQuery({
    queryKey: ['activity', 'actors'],
    retry: false,
    queryFn: async () =>
      (
        await httpClient.get<{ items: string[] }>(`${base()}/actors`, {
          errorPresentation: 'inline',
        })
      ).data.items,
  });
}

/** Same-origin GET that the browser downloads; the session cookie authenticates it. */
export function activityExportUrl(filters: ActivityFilters): string {
  return `${base()}/export?${buildActivityQuery(filters)}`;
}
