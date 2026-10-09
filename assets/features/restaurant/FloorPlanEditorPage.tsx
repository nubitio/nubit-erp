import React, { useRef, useState } from 'react';
import './RestaurantPrototype.scss';
import { shapeFor } from './tableShape';
import { useAreaTabs } from './useAreaTabs';
import { useTableCanvasPositions } from './useTableCanvasPositions';
import { toLogical, useResponsiveCanvasScale } from './useResponsiveCanvasScale';
import { useRestaurantData, useUpdateTablePosition } from './api/useRestaurantData';
import type { RestaurantTableMock } from './types';

const CANVAS_WIDTH = 1000;
const CANVAS_HEIGHT = 600;
const TABLE_FOOTPRINT = 60;

function clamp(value: number, max: number): number {
  return Math.max(0, Math.min(max - TABLE_FOOTPRINT, value));
}

interface DragState {
  tableId: string;
  pointerId: number;
  offsetX: number;
  offsetY: number;
}

export function FloorPlanEditorPage() {
  const { tables } = useRestaurantData();
  const { tabs, activeAreaId, setActiveAreaId, tablesInActiveArea } = useAreaTabs(tables);
  const basePositions = useTableCanvasPositions(tablesInActiveArea);
  const updatePosition = useUpdateTablePosition();
  const { containerRef, scale } = useResponsiveCanvasScale(CANVAS_WIDTH);
  const canvasRef = useRef<HTMLDivElement>(null);
  const dragRef = useRef<DragState | null>(null);
  const [localPositions, setLocalPositions] = useState<Record<string, { x: number; y: number }>>(
    {},
  );

  const positionFor = (table: RestaurantTableMock): { x: number; y: number } =>
    localPositions[table.id] ?? basePositions.get(table.id) ?? { x: 40, y: 40 };

  const handlePointerDown = (
    event: React.PointerEvent<HTMLButtonElement>,
    table: RestaurantTableMock,
  ) => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const canvasRect = canvas.getBoundingClientRect();
    const current = positionFor(table);
    dragRef.current = {
      tableId: table.id,
      pointerId: event.pointerId,
      offsetX: toLogical(event.clientX, canvasRect.left, scale) - current.x,
      offsetY: toLogical(event.clientY, canvasRect.top, scale) - current.y,
    };
    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const handlePointerMove = (event: React.PointerEvent<HTMLButtonElement>) => {
    const drag = dragRef.current;
    const canvas = canvasRef.current;
    if (!drag || !canvas || drag.pointerId !== event.pointerId) return;
    const canvasRect = canvas.getBoundingClientRect();
    const x = clamp(toLogical(event.clientX, canvasRect.left, scale) - drag.offsetX, CANVAS_WIDTH);
    const y = clamp(toLogical(event.clientY, canvasRect.top, scale) - drag.offsetY, CANVAS_HEIGHT);
    setLocalPositions((prev) => ({ ...prev, [drag.tableId]: { x, y } }));
  };

  const endDrag = (event: React.PointerEvent<HTMLButtonElement>) => {
    const drag = dragRef.current;
    if (!drag || drag.pointerId !== event.pointerId) return;
    dragRef.current = null;
    const finalPosition = localPositions[drag.tableId];
    if (finalPosition) {
      updatePosition.mutate({ tableId: drag.tableId, x: finalPosition.x, y: finalPosition.y });
    }
  };

  return (
    <div className="restaurant-proto restaurant-floor-admin">
      <header className="restaurant-floor__header">
        <div>
          <h1>Plano del local</h1>
          <p>
            Arrastra cada mesa a su lugar real. Las mesas sin ubicar aparecen apiladas arriba a la
            izquierda.
          </p>
        </div>
      </header>

      {tabs.length === 0 ? (
        <p className="restaurant-floor__empty">
          Crea primero un ambiente en &ldquo;Ambientes y plantas&rdquo; y algunas mesas en
          &ldquo;Configurar mesas&rdquo;.
        </p>
      ) : (
        <>
          <div className="restaurant-area-tabs" role="tablist">
            {tabs.map(({ area, label }) => (
              <button
                key={area.id}
                type="button"
                role="tab"
                aria-selected={area.id === activeAreaId}
                className={area.id === activeAreaId ? 'is-active' : ''}
                onClick={() => setActiveAreaId(area.id)}
              >
                {label}
              </button>
            ))}
          </div>

          <div
            ref={containerRef}
            className="restaurant-floor-editor-wrap"
            style={{ height: CANVAS_HEIGHT * scale }}
          >
            <div
              ref={canvasRef}
              className="restaurant-floor-editor"
              style={{ width: CANVAS_WIDTH, height: CANVAS_HEIGHT, transform: `scale(${scale})` }}
            >
              {tablesInActiveArea.map((table) => {
                const position = positionFor(table);
                return (
                  <button
                    key={table.id}
                    type="button"
                    className={`restaurant-table restaurant-table--${shapeFor(table.seats)} is-${table.status}`}
                    style={{ left: position.x, top: position.y, touchAction: 'none' }}
                    onPointerDown={(event) => handlePointerDown(event, table)}
                    onPointerMove={handlePointerMove}
                    onPointerUp={endDrag}
                    onPointerCancel={endDrag}
                  >
                    <span className="restaurant-table__code">{table.code}</span>
                    <span className="restaurant-table__seats">{table.seats} pers.</span>
                  </button>
                );
              })}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export default FloorPlanEditorPage;
