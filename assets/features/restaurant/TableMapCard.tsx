import React from 'react';
import type { RestaurantTableMock, TableStatus } from './types';
import { shapeFor } from './tableShape';

const STATUS_LABEL: Record<TableStatus, string> = {
  libre: 'libre',
  ocupada: 'ocupada',
  por_cobrar: 'por cobrar',
};

interface TableMapCardProps {
  table: RestaurantTableMock;
  itemCount: number;
  onClick: () => void;
  style?: React.CSSProperties;
}

export function TableMapCard({ table, itemCount, onClick, style }: TableMapCardProps) {
  return (
    <button
      type="button"
      className={`restaurant-table restaurant-table--${shapeFor(table.seats)} is-${table.status}`}
      onClick={onClick}
      style={style}
    >
      {itemCount > 0 && <span className="restaurant-table__count">{itemCount}</span>}
      <span className="restaurant-table__code">{table.code}</span>
      <span className="restaurant-table__seats">{table.seats} pers.</span>
      <span className="restaurant-table__status">{STATUS_LABEL[table.status]}</span>
    </button>
  );
}
