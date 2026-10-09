import React, { useState } from 'react';
import type { PosItem } from './types';
import { formatStockCount, productNameInitials } from './posPresentation';
import './POSPage.scss';

interface POSItemProps {
  item: PosItem;
  onAdd: (item: PosItem) => void | Promise<void>;
  onCatalogPriceEdit?: (item: PosItem, nextPrice: number) => void | Promise<void>;
  quantity: number;
  stockOverride?: number;
  enforceStockCheck?: boolean;
  isCatalogPriceUpdating?: boolean;
}

export function POSItem({
  item,
  onAdd,
  onCatalogPriceEdit,
  quantity,
  stockOverride,
  enforceStockCheck = true,
  isCatalogPriceUpdating = false,
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
  const [editingPrice, setEditingPrice] = useState(false);
  const [editingPriceValue, setEditingPriceValue] = useState('');

  function triggerAdd() {
    if (isOutOfStock || editingPrice) return;
    setIsAdding(true);
    setTimeout(() => setIsAdding(false), 320);
    void onAdd(item);
  }

  function openPriceEditor(event: React.MouseEvent) {
    event.stopPropagation();
    setEditingPriceValue(unitPrice.toFixed(2));
    setEditingPrice(true);
  }

  function cancelPriceEditor() {
    setEditingPrice(false);
    setEditingPriceValue('');
  }

  async function savePriceEdit() {
    const nextPrice = Number.parseFloat(editingPriceValue);
    if (!Number.isFinite(nextPrice) || nextPrice < 0 || !onCatalogPriceEdit) {
      cancelPriceEditor();
      return;
    }

    await onCatalogPriceEdit(item, nextPrice);
    cancelPriceEditor();
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
      {/* Top row: thumbnail + stock / qty */}
      <div className="pos-product-card__top">
        <div className="pos-product-card__thumb" aria-hidden="true">
          {item.photo?.path ? (
            <img src={item.photo.path} alt="" className="pos-product-card__image" loading="lazy" />
          ) : (
            <span>{productNameInitials(item.name)}</span>
          )}
        </div>
        {quantity > 0 ? (
          <div className="pos-product-card__qty-badge" aria-label={`${quantity} en la orden`}>
            {quantity}
          </div>
        ) : isOutOfStock ? (
          <div className="pos-product-card__out-pill">Sin stock</div>
        ) : (
          tracksInventory &&
          stockValue !== null &&
          stockValue > 0 && (
            <div className="pos-product-card__stock-corner">{formatStockCount(stockValue)}</div>
          )
        )}
      </div>

      <div className="pos-product-card__title" title={item.name}>
        {item.name}
      </div>
      {item.sku ? <div className="pos-product-card__sku">{item.sku}</div> : null}

      {/* Price */}
      <div className="pos-product-card__price-row" onClick={(event) => event.stopPropagation()}>
        {editingPrice ? (
          <div className="pos-product-card__price-edit">
            <input
              type="number"
              min={0}
              step="0.10"
              value={editingPriceValue}
              autoFocus
              disabled={isCatalogPriceUpdating}
              onChange={(event) => setEditingPriceValue(event.target.value)}
              onFocus={(event) => event.currentTarget.select()}
              onKeyDown={(event) => {
                if (event.key === 'Enter') void savePriceEdit();
                if (event.key === 'Escape') cancelPriceEditor();
              }}
              aria-label={`Nuevo precio de ${item.name}`}
            />
            <button
              type="button"
              onClick={() => void savePriceEdit()}
              disabled={isCatalogPriceUpdating}
              aria-label="Guardar precio"
            >
              <i className="ph ph-check" aria-hidden="true" />
            </button>
          </div>
        ) : (
          <>
            <div className="pos-product-card__price">S/{unitPrice.toFixed(2)}</div>
            {onCatalogPriceEdit && (
              <button
                type="button"
                className="pos-product-card__edit-price"
                onClick={openPriceEditor}
                disabled={isCatalogPriceUpdating}
                aria-label={`Editar precio maestro de ${item.name}`}
                title="Editar precio del catálogo"
              >
                <i className="ph ph-pencil-simple" aria-hidden="true" />
              </button>
            )}
          </>
        )}
      </div>
    </article>
  );
}
