import React, { useState } from 'react';
import type { PosItem } from './types';
import './POSPage.scss';

function nameToInitials(name: string): string {
  const words = name.trim().split(/\s+/);
  if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

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
  const tracksInventory = item.trackInventory !== false;
  const isOutOfStock =
    tracksInventory && enforceStockCheck && stockValue !== null && stockValue <= 0;
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

  return (
    <article
      className={`pos-product-card${isOutOfStock ? ' is-disabled' : ''}${quantity > 0 ? ' is-in-cart' : ''}${isAdding ? ' is-adding' : ''}`}
      onClick={triggerAdd}
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
      data-testid="pos-product-card"
      data-product-id={item.id}
    >
      {/* Top row: thumbnail + stock/OUT */}
      <div className="pos-product-card__top">
        <div className="pos-product-card__thumb" aria-hidden="true">
          {item.photo?.path ? (
            <img src={item.photo.path} alt="" className="pos-product-card__image" loading="lazy" />
          ) : (
            <span>{nameToInitials(item.name)}</span>
          )}
        </div>
        {isOutOfStock ? (
          <div className="pos-product-card__out-pill">OUT</div>
        ) : (
          tracksInventory && stockValue !== null && stockValue > 0 && (
            <div className="pos-product-card__stock-corner">
              {stockValue % 1 === 0 ? stockValue.toFixed(0) : stockValue.toFixed(1)} left
            </div>
          )
        )}
      </div>

      {/* Name */}
      <div className="pos-product-card__title" title={item.name}>
        {item.name}
      </div>

      {/* Price */}
      <div className="pos-product-card__price">S/{unitPrice.toFixed(2)}</div>
    </article>
  );
}
