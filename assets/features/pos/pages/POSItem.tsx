import React, { useState } from 'react';
import type { PosItem } from './types';
import './POSPage.scss';

// Muted, desaturated palette — professional look for B2B POS
const AVATAR_COLORS = [
  '#4a6fa5',
  '#3d8a74',
  '#7a6099',
  '#4a8ab5',
  '#6a7f5e',
  '#a06050',
  '#526a7d',
  '#7d7040',
];

function nameToColor(name: string): string {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xfffffff;
  return AVATAR_COLORS[h % AVATAR_COLORS.length];
}

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
  const isLowStock = tracksInventory && !isOutOfStock && stockValue !== null && stockValue <= 3;
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
      data-testid="pos-product-card"
      data-product-id={item.id}
    >
      {/* Image with status badges */}
      <div className="pos-product-card__media">
        {item.photo?.path ? (
          <img
            src={item.photo.path}
            alt={item.name}
            className="pos-product-card__image"
            loading="lazy"
          />
        ) : (
          <div
            className="pos-product-card__no-photo"
            style={{ background: nameToColor(item.name) }}
            aria-hidden="true"
          >
            {nameToInitials(item.name)}
          </div>
        )}
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
          {tracksInventory && stockValue !== null && !isOutOfStock && (
            <div className="pos-product-card__stock" title="Stock disponible">
              {stockValue % 1 === 0
                ? stockValue.toFixed(0)
                : stockValue.toFixed(1)}
            </div>
          )}
          {!isOutOfStock && (
            <button
              type="button"
              className="pos-product-card__add"
              onClick={(e) => {
                e.stopPropagation();
                triggerAdd();
              }}
              tabIndex={-1}
              aria-label={`Agregar ${item.name}`}
              data-testid="pos-product-card-add"
            >
              +
            </button>
          )}
        </div>
      </div>
    </article>
  );
}
