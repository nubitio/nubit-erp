import React, { useState } from 'react';
import type { PosItem } from './types';
import './POSPage.scss';

interface POSItemProps {
  item: PosItem;
  onAdd: (item: PosItem) => void | Promise<void>;
  quantity: number;
  stockOverride?: number;
  enforceStockCheck?: boolean;
}

export function POSItem({
  item,
  onAdd,
  quantity,
  stockOverride,
  enforceStockCheck = true,
}: POSItemProps) {
  const stockSource = stockOverride ?? item.stock;
  const stockValue = typeof stockSource === 'number' ? stockSource : null;
  const isOutOfStock = enforceStockCheck && stockValue !== null && stockValue <= 0;
  const isLowStock = !isOutOfStock&&stockValue!==null&&stockValue<=3;
  const unitPrice =
    typeof item.salePrice === 'number'
      ? item.salePrice
      : Number.parseFloat(String(item.salePrice ?? 0)) || 0;

  const [isAdding, setIsAdding] = useState(false);

  function triggerAdd() {
    if (isOutOfStock) return;
    setIsAdding(true);
    setTimeout(() => setIsAdding(false), 320);
    void onAdd(item);
  }

  function handleCardClick(e: React.MouseEvent) {
    if ((e.target as HTMLElement).closest('.pos-product-card__add')) return;
    triggerAdd();
  }

  return (
    <article
      className={`pos-product-card${isOutOfStock ? ' is-disabled' : ''}${quantity > 0 ? ' is-in-cart' : ''}${isAdding ? ' is-adding' : ''}`}
      onClick={handleCardClick}
      role="button"
      tabIndex={isOutOfStock ? -1 : 0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          triggerAdd();
        }
      }}
      aria-label={`${item.name} — S/${unitPrice.toFixed(2)}${quantity > 0 ? ` (${quantity} en carrito)` : ''}`}
      aria-pressed={quantity > 0}
    >
      {/* Image with status badges */}
      <div className="pos-product-card__media">
        <img
          src={
            item.photo?.path ||
            `https://ui-avatars.com/api/?name=${encodeURIComponent(item.name)}&bold=true&size=128`
          }
          alt={item.name}
          className="pos-product-card__image"
          loading="lazy"
        />
        {quantity > 0 && (
          <div className="pos-product-card__qty-badge" aria-label={`${quantity} en carrito`}>
            {quantity}
          </div>
        )}
        {isLowStock && <div className="pos-product-card__low-badge">Ultimas</div>}
        {isOutOfStock && <div className="pos-product-card__out-badge">Sin stock</div>}
      </div>

      {/* Name + price row */}
      <div className="pos-product-card__body">
        <div className="pos-product-card__title" title={item.name}>
          {item.name}
        </div>
        <div className="pos-product-card__footer">
          <div className="pos-product-card__price">S/{unitPrice.toFixed(2)}</div>
          {!isOutOfStock&&(
            <button
              type="button"
              className="pos-product-card__add"
              onClick={(e) => {
                e.stopPropagation();
                triggerAdd();
              }}
              tabIndex={-1}
              aria-label={`Agregar ${item.name}`}
            >
              +
            </button>
          )}
        </div>
      </div>
    </article>
  );
}
