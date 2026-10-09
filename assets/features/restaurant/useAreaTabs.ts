import { useMemo, useState } from 'react';
import { useRestaurantAreas } from './api/useRestaurantData';
import type { RestaurantAreaMock, RestaurantTableMock } from './types';

export interface AreaTab {
  area: RestaurantAreaMock;
  label: string;
}

interface UseAreaTabsResult {
  tabs: AreaTab[];
  activeAreaId: string | null;
  setActiveAreaId: (id: string) => void;
  tablesInActiveArea: RestaurantTableMock[];
  isLoading: boolean;
}

/**
 * Shared by the floor-plan editor and the operational table map so both
 * always agree on the same tab order — one tab per `RestaurantArea`, sorted
 * by `(floor, orderIndex)`. A restaurant with a single floor never shows the
 * "Planta N" prefix; one with several floors does, on every tab.
 */
export function useAreaTabs(tables: RestaurantTableMock[]): UseAreaTabsResult {
  const { data: areas = [], isLoading } = useRestaurantAreas();
  const [manualAreaId, setManualAreaId] = useState<string | null>(null);

  const sortedAreas = useMemo(
    () => [...areas].sort((a, b) => a.floor - b.floor || a.orderIndex - b.orderIndex),
    [areas],
  );

  const hasMultipleFloors = useMemo(
    () => new Set(sortedAreas.map((a) => a.floor)).size > 1,
    [sortedAreas],
  );

  const tabs = useMemo<AreaTab[]>(
    () =>
      sortedAreas.map((area) => ({
        area,
        label: hasMultipleFloors ? `Planta ${area.floor} · ${area.name}` : area.name,
      })),
    [sortedAreas, hasMultipleFloors],
  );

  const activeAreaId =
    manualAreaId && tabs.some((t) => t.area.id === manualAreaId)
      ? manualAreaId
      : (tabs[0]?.area.id ?? null);

  const tablesInActiveArea = useMemo(
    () => (activeAreaId ? tables.filter((t) => t.areaId === activeAreaId) : []),
    [tables, activeAreaId],
  );

  return { tabs, activeAreaId, setActiveAreaId: setManualAreaId, tablesInActiveArea, isLoading };
}
