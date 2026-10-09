import React, { useEffect, useState } from 'react';
import './RestaurantPrototype.scss';
import { KitchenTicketCard } from './KitchenTicketCard';
import { useMarkItemReady, useRestaurantData } from './api/useRestaurantData';

export function KitchenDisplayPage() {
  const { tables, orders } = useRestaurantData();
  const markReadyMutation = useMarkItemReady();
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const id = window.setInterval(() => setNow(Date.now()), 15000);
    return () => window.clearInterval(id);
  }, []);

  const tickets = tables
    .filter((t) => t.currentOrderId)
    .map((t) => {
      const order = orders[t.currentOrderId as string];
      const kitchenItems =
        order?.items.filter((it) => it.status === 'enviado' || it.status === 'listo') ?? [];
      return { table: t, items: kitchenItems };
    })
    .filter((t) => t.items.length > 0);

  return (
    <div className="restaurant-proto restaurant-kds">
      <header className="restaurant-kds__header">
        <h1>Cocina</h1>
      </header>

      {tickets.length === 0 ? (
        <p className="restaurant-kds__empty">
          Sin comandas en curso — aquí aparecen apenas un mesero envíe un pedido.
        </p>
      ) : (
        <div className="restaurant-kds__grid">
          {tickets.map(({ table, items }) => (
            <KitchenTicketCard
              key={table.id}
              tableCode={table.code}
              items={items}
              now={now}
              onMarkReady={(itemId) => markReadyMutation.mutate(itemId)}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export default KitchenDisplayPage;
