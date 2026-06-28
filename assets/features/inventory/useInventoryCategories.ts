import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { categoryEndpoints } from '@features/catalog/api/catalogEndpoints';

export interface InventoryCategoryOption {
  id: string;
  name: string;
}

function extractCategories(raw: unknown): InventoryCategoryOption[] {
  let items: unknown[] = [];
  if (Array.isArray(raw)) {
    items = raw;
  } else if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>;
    if (Array.isArray(obj['hydra:member'])) items = obj['hydra:member'] as unknown[];
    else if (Array.isArray(obj['items'])) items = obj['items'] as unknown[];
    else if (Array.isArray(obj['data'])) items = obj['data'] as unknown[];
  }

  return items
    .map((item) => {
      const row = item as Record<string, unknown>;
      return {
        id: String(row['id'] ?? ''),
        name: String(row['name'] ?? ''),
      };
    })
    .filter((item) => item.id && item.name)
    .sort((a, b) => a.name.localeCompare(b.name, 'es'));
}

export function useInventoryCategories() {
  return useQuery({
    queryKey: ['inventory-categories'],
    queryFn: async () => {
      const response = await httpClient.get<unknown>(
        `${categoryEndpoints.list()}?pagination=false`,
      );
      return extractCategories(response.data);
    },
    staleTime: 5 * 60_000,
  });
}