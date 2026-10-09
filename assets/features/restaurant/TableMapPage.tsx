import React from 'react';
import { useNavigate } from 'react-router-dom';
import './RestaurantPrototype.scss';
import { TableMapCard } from './TableMapCard';
import { useAreaTabs } from './useAreaTabs';
import { useTableCanvasPositions } from './useTableCanvasPositions';
import { useResponsiveCanvasScale } from './useResponsiveCanvasScale';
import { useOpenTable, useRestaurantData } from './api/useRestaurantData';

const CANVAS_WIDTH = 1000;
const CANVAS_HEIGHT = 600;

export function TableMapPage() {
  const navigate = useNavigate();
  const { tables, orders } = useRestaurantData();
  const { tabs, activeAreaId, setActiveAreaId, tablesInActiveArea } = useAreaTabs(tables);
  const positions = useTableCanvasPositions(tablesInActiveArea);
  const { containerRef, scale } = useResponsiveCanvasScale(CANVAS_WIDTH);
  const openTableMutation = useOpenTable();

  const handleOpen = (tableId: string, isFree: boolean) => {
    if (isFree) {
      openTableMutation.mutate(tableId, {
        onSettled: () => navigate(`/restaurant/mesa/${tableId}`),
      });
      return;
    }
    navigate(`/restaurant/mesa/${tableId}`);
  };

  return (
    <div className="restaurant-proto restaurant-floor">
      <header className="restaurant-floor__header">
        <div>
          <h1>El piso</h1>
          <p>Toca una mesa para abrir su comanda</p>
        </div>
      </header>

      {tabs.length === 0 ? (
        <p className="restaurant-floor__empty">
          Aún no hay ambientes configurados — créalos en &ldquo;Ambientes y plantas&rdquo;, agrega
          mesas en &ldquo;Configurar mesas&rdquo; y ubícalas en &ldquo;Plano del local&rdquo;.
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
              className="restaurant-floor-editor restaurant-floor-editor--readonly"
              style={{ width: CANVAS_WIDTH, height: CANVAS_HEIGHT, transform: `scale(${scale})` }}
            >
              {tablesInActiveArea.map((table) => {
                const position = positions.get(table.id) ?? { x: 40, y: 40 };
                return (
                  <TableMapCard
                    key={table.id}
                    table={table}
                    itemCount={
                      table.currentOrderId ? (orders[table.currentOrderId]?.items.length ?? 0) : 0
                    }
                    onClick={() => handleOpen(table.id, table.status === 'libre')}
                    style={{ left: position.x, top: position.y }}
                  />
                );
              })}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export default TableMapPage;
