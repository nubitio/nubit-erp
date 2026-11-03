import React, { useState } from 'react';
import type { PosItem } from './types';
import { formatStockCount } from './posPresentation';
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

  const [failedPhoto, setFailedPhoto] = useState<string | null>(null);
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
      data-testid="pos-product-card"
      data-product-id={item.id}
    >
      <button
        type="button"
        className="pos-product-card__select"
        onClick={triggerAdd}
        disabled={isOutOfStock || editingPrice}
        aria-label={`Agregar ${item.name} — S/ ${unitPrice.toFixed(2)}${quantity > 0 ? ` (${quantity} en carrito)` : ''}`}
        aria-pressed={quantity > 0}
        data-testid="pos-product-card-add"
      >
        <span className="pos-product-card__thumb" aria-hidden="true">
          {item.photo?.path && failedPhoto !== item.photo.path ? (
            <img
              src={item.photo.path}
              alt=""
              className="pos-product-card__image"
              loading="lazy"
              onError={() => setFailedPhoto(item.photo!.path)}
            />
          ) : (
            <i className="ph ph-cube" />
          )}
        </span>
        <span className="pos-product-card__title">{item.name}</span>
        {item.sku && <span className="pos-product-card__sku">{item.sku}</span>}
        <span className="pos-product-card__price-row">
          <span className="pos-product-card__price">S/ {unitPrice.toFixed(2)}</span>
          {quantity > 0 ? (
            <span className="pos-product-card__qty-badge" aria-label={`${quantity} en la orden`}>
              {quantity}
            </span>
          ) : isOutOfStock ? (
            <span className="pos-product-card__stock-note is-out">Agotado</span>
          ) : tracksInventory && stockValue !== null && stockValue > 0 && stockValue <= 5 ? (
            <span className="pos-product-card__stock-note">
              Quedan {formatStockCount(stockValue)}
            </span>
          ) : tracksInventory && stockValue !== null ? (
            <span className="pos-product-card__stock">{formatStockCount(stockValue)} en stock</span>
          ) : null}
        </span>
      </button>
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
          <button type="button" onClick={cancelPriceEditor} aria-label="Cancelar cambio de precio">
            <i className="ph ph-x" aria-hidden="true" />
          </button>
        </div>
      ) : (
        onCatalogPriceEdit && (
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
        )
      )}
    </article>
  );
}
