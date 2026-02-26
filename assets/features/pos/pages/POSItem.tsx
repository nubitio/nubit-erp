import React from 'react';
import { Minus, Plus } from 'lucide-react';
import type { PosItem } from './types';
import './POSPage.scss';

interface POSItemProps {
  item: PosItem;
  onAdd: (item: PosItem) => void | Promise<void>;
  onRemove: (id: string) => void;
  quantity: number;
  stockOverride?: number;
  enforceStockCheck?: boolean;
}

export function POSItem({
  item,
  onAdd,
  onRemove,
  quantity,
  stockOverride,
  enforceStockCheck = true,
}: POSItemProps) {
  const stockSource = stockOverride ?? item.stock;
  const stockValue = typeof stockSource === 'number' ? stockSource : null;
  const isOutOfStock = enforceStockCheck && stockValue !== null && stockValue <= 0;
  const unitPrice =
    typeof item.salePrice === 'number'
      ? item.salePrice
      : Number.parseFloat(String(item.salePrice ?? 0)) || 0;

  return (
    <article className={`pos-product-card ${isOutOfStock ? 'is-disabled' : ''}`}>
      <div className="pos-product-card__media">
        <img
          src={item.photo?.path || `https://ui-avatars.com/api/?name=${encodeURIComponent(item.name)}`}
          alt={item.name}
          className="pos-product-card__image"
        />
      </div>

      <div className="pos-product-card__body">
        <div className="pos-product-card__title" title={item.name}>
          {item.name}
        </div>
        <div className="pos-product-card__meta">
          {item.sku && <span>{item.sku}</span>}
          {stockValue !== null && (
            <span className={stockValue <= 3 ? 'is-low' : ''}>Stock: {stockValue}</span>
          )}
        </div>
      </div>

      <div className="pos-product-card__footer">
        <span className="pos-product-card__price">S/{unitPrice.toFixed(2)}</span>
        <div className="pos-stepper" aria-label={`Cantidad de ${item.name}`}>
          <button
            type="button"
            onClick={() => onRemove(item.id)}
            className="pos-stepper__btn"
            disabled={quantity <= 0}
            aria-label={`Quitar ${item.name}`}
          >
            <Minus size={16} />
          </button>
          <span className="pos-stepper__value">{quantity || 0}</span>
          <button
            type="button"
            onClick={() => onAdd(item)}
            className={`pos-stepper__btn pos-stepper__btn--primary ${quantity > 0 ? 'is-active' : ''}`}
            disabled={isOutOfStock}
            aria-label={`Agregar ${item.name}`}
          >
            <Plus size={16} />
          </button>
        </div>
      </div>
    </article>
  );
}
