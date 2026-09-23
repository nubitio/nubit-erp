import React from 'react';
import type { OrderItemMock, OrderItemStatus } from './types';

const STATUS_LABEL: Record<OrderItemStatus, string> = {
  pendiente: 'nuevo',
  enviado: 'en cocina',
  listo: 'listo',
  entregado: 'entregado',
};

interface OrderItemRowProps {
  item: OrderItemMock;
  price: number;
  onMarkDelivered: () => void;
}

export function OrderItemRow({ item, price, onMarkDelivered }: OrderItemRowProps) {
  return (
    <div className={`restaurant-ticket__line is-${item.status}`}>
      <span className="restaurant-ticket__line-qty">{item.qty}×</span>
      <span className="restaurant-ticket__line-name">{item.productName}</span>
      <span className="restaurant-ticket__line-price">{(price * item.qty).toFixed(2)}</span>
      <span className="restaurant-ticket__line-status">{STATUS_LABEL[item.status]}</span>
      {item.status === 'listo' && (
        <button type="button" className="restaurant-ticket__line-action" onClick={onMarkDelivered}>
          Entregar
        </button>
      )}
    </div>
  );
}
