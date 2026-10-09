import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import toast from '@shared/lib/SharedUtils';
import './RestaurantPrototype.scss';
import { OrderItemRow } from './OrderItemRow';
import {
  useAddOrderItem,
  useMarkItemDelivered,
  useRequestBill,
  useRestaurantData,
  useRestaurantMenu,
  useSendToKitchen,
} from './api/useRestaurantData';
import { useCheckoutOrder } from './api/useCheckoutOrder';

export function OrderPage() {
  const { tableId } = useParams<{ tableId: string }>();
  const navigate = useNavigate();
  const { tables, orders } = useRestaurantData();
  const { data: menu = [] } = useRestaurantMenu();
  const addItemMutation = useAddOrderItem();
  const sendToKitchenMutation = useSendToKitchen();
  const markDeliveredMutation = useMarkItemDelivered();
  const requestBillMutation = useRequestBill();
  const checkoutMutation = useCheckoutOrder();
  const [activeCategory, setActiveCategory] = useState<string | null>(null);

  const table = tables.find((t) => t.id === tableId);
  const order = table?.currentOrderId ? orders[table.currentOrderId] : undefined;
  const categories = Array.from(new Set(menu.map((m) => m.category)));
  const visibleMenu = activeCategory ? menu.filter((m) => m.category === activeCategory) : menu;
  const hasPending = order?.items.some((it) => it.status === 'pendiente') ?? false;
  const total = order?.items.reduce((sum, it) => sum + Number(it.unitPrice) * it.qty, 0) ?? 0;

  if (!table) {
    return (
      <div className="restaurant-proto restaurant-order">
        <p>Mesa no encontrada.</p>
        <button
          type="button"
          className="restaurant-btn restaurant-btn--lime"
          onClick={() => navigate('/restaurant/mesas')}
        >
          Ir al piso
        </button>
      </div>
    );
  }

  const handleCheckout = () => {
    if (!order) return;
    checkoutMutation.mutate(
      { orderId: order.id, items: order.items },
      {
        onSuccess: () => navigate('/restaurant/mesas'),
        onError: (error) => {
          toast(
            error instanceof Error ? error.message : 'No se pudo registrar la venta.',
            'error',
            6000,
          );
        },
      },
    );
  };

  return (
    <div className="restaurant-proto restaurant-order">
      <header className="restaurant-order__header">
        <button
          type="button"
          className="restaurant-order__back"
          onClick={() => navigate('/restaurant/mesas')}
          aria-label="Volver al piso"
        >
          ←
        </button>
        <div>
          <h1>Mesa {table.code}</h1>
          <p>
            {table.area} · {table.seats} personas
          </p>
        </div>
      </header>

      <div className="restaurant-order__body">
        <section className="restaurant-menu">
          <div className="restaurant-menu__tabs">
            <button
              type="button"
              className={activeCategory === null ? 'is-active' : ''}
              onClick={() => setActiveCategory(null)}
            >
              Todos
            </button>
            {categories.map((category) => (
              <button
                key={category}
                type="button"
                className={activeCategory === category ? 'is-active' : ''}
                onClick={() => setActiveCategory(category)}
              >
                {category}
              </button>
            ))}
          </div>
          <div className="restaurant-menu__grid">
            {visibleMenu.map((item) => (
              <button
                key={item.id}
                type="button"
                className="restaurant-dish"
                disabled={!order || addItemMutation.isPending}
                onClick={() =>
                  order && addItemMutation.mutate({ orderId: order.id, productId: item.id, qty: 1 })
                }
              >
                <span className="restaurant-dish__name">{item.name}</span>
                <span className="restaurant-dish__price">S/ {item.price.toFixed(2)}</span>
              </button>
            ))}
          </div>
        </section>

        <aside className="restaurant-ticket">
          <div className="restaurant-ticket__paper">
            <div className="restaurant-ticket__head">
              <span>COMANDA</span>
              <span>MESA {table.code}</span>
            </div>
            {!order || order.items.length === 0 ? (
              <p className="restaurant-ticket__empty">Toca un plato para empezar la comanda.</p>
            ) : (
              <div className="restaurant-ticket__lines">
                {order.items.map((item) => (
                  <OrderItemRow
                    key={item.id}
                    item={item}
                    price={Number(item.unitPrice)}
                    onMarkDelivered={() => markDeliveredMutation.mutate(item.id)}
                  />
                ))}
              </div>
            )}
            {order && order.items.length > 0 && (
              <div className="restaurant-ticket__total">
                <span>Total</span>
                <span>S/ {total.toFixed(2)}</span>
              </div>
            )}
          </div>

          <div className="restaurant-order__actions">
            <button
              type="button"
              className="restaurant-btn restaurant-btn--lime"
              disabled={!order || !hasPending || sendToKitchenMutation.isPending}
              onClick={() => order && sendToKitchenMutation.mutate(order.id)}
            >
              Enviar a cocina
            </button>
            <button
              type="button"
              className="restaurant-btn restaurant-btn--teal"
              disabled={
                table.status !== 'ocupada' || !order?.items.length || requestBillMutation.isPending
              }
              onClick={() => order && requestBillMutation.mutate(order.id)}
            >
              Pedir la cuenta
            </button>
            <button
              type="button"
              className="restaurant-btn restaurant-btn--ink"
              disabled={table.status !== 'por_cobrar' || checkoutMutation.isPending}
              onClick={handleCheckout}
            >
              {checkoutMutation.isPending ? 'Cobrando…' : 'Cobrar y liberar mesa'}
            </button>
          </div>
        </aside>
      </div>
    </div>
  );
}

export default OrderPage;
