import { useMemo } from 'react';
import type { RestaurantTableMock } from './types';

const UNPLACED_STACK_GAP = 90;

function defaultPosition(unplacedIndex: number): { x: number; y: number } {
  return { x: 40, y: 40 + unplacedIndex * UNPLACED_STACK_GAP };
}

/**
 * Where each table sits on the floor-plan canvas: its saved position, or a
 * stacked default (top-left corner) for a table the owner hasn't placed yet
 * in `/restaurant/admin/plano`. Shared by the editor and the operational
 * table map so both agree on the same layout.
 */
export function useTableCanvasPositions(tables: RestaurantTableMock[]): Map<string, { x: number; y: number }> {
  return useMemo(() => {
    const map = new Map<string, { x: number; y: number }>();
    let unplacedIndex = 0;
    for (const table of tables) {
      if (table.positionX != null && table.positionY != null) {
        map.set(table.id, { x: table.positionX, y: table.positionY });
      } else {
        map.set(table.id, defaultPosition(unplacedIndex));
        unplacedIndex += 1;
      }
    }
    return map;
  }, [tables]);
}
