import React, { useEffect } from 'react';
import { POSItem } from './POSItem';
import type { Category, PosItem } from './types';

interface POSCatalogPanelProps {
  loading: boolean;
  loadError: string | null;
  searchQuery: string;
  setSearchQuery: (value: string) => void;
  cartItemsCount: number;
  filteredMenu: PosItem[];
  categories: Category[];
  activeCategory: string | null;
  setActiveCategory: (value: string | null) => void;
  onAddToCart: (item: PosItem) => void | Promise<void>;
  getItemQuantity: (id: string) => number;
  getEffectiveStock: (item: PosItem) => number | undefined;
  enforceStockCheck: boolean;
  searchInputRef: React.RefObject<HTMLInputElement | null>;
  usingOfflineCatalog: boolean;
  lastCatalogSyncAt: string | null;
}

export function POSCatalogPanel({
  loading,
  loadError,
  searchQuery,
  setSearchQuery,
  cartItemsCount,
  filteredMenu,
  categories,
  activeCategory,
  setActiveCategory,
  onAddToCart,
  getItemQuantity,
  getEffectiveStock,
  enforceStockCheck,
  searchInputRef,
  usingOfflineCatalog,
  lastCatalogSyncAt,
}: POSCatalogPanelProps) {
  // F2 or '/' to focus search; Escape to clear
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if ((e.key === 'F2' || e.key === '/') && document.activeElement !== searchInputRef.current) {
        e.preventDefault();
        searchInputRef.current?.focus();
      }
      if (e.key === 'Escape' && document.activeElement === searchInputRef.current) {
        setSearchQuery('');
        searchInputRef.current?.blur();
      }
    }
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [setSearchQuery, searchInputRef]);

  return (
    <section className="pos-catalog">
      {/* ── Search toolbar ── */}
      <header className="pos-toolbar">
        <div className="pos-toolbar__search">
          <i className="ph ph-magnifying-glass" aria-hidden="true" />
          <input
            ref={searchInputRef}
            type="search"
            placeholder="Buscar producto… (F2)"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && filteredMenu.length > 0 && !loading) {
                e.preventDefault();
                void onAddToCart(filteredMenu[0]);
              }
            }}
            autoComplete="off"
            autoFocus
            data-testid="pos-product-search"
          />
          {searchQuery && (
            <button
              type="button"
              className="pos-toolbar__search-clear"
              onClick={() => setSearchQuery('')}
              aria-label="Limpiar búsqueda"
            >
              ×
            </button>
          )}
        </div>
        {cartItemsCount > 0 && (
          <div className="pos-toolbar__cart-count" aria-live="polite">
            {cartItemsCount} en orden
          </div>
        )}
      </header>

      {/* Category bar — fixed outside scroll, single-row, horizontally scrollable */}
      {usingOfflineCatalog && (
        <div className="pos-offline-banner" role="status">
          Mostrando catálogo offline
          {lastCatalogSyncAt
            ? ` (última actualización: ${new Date(lastCatalogSyncAt).toLocaleString('es-PE')})`
            : ''}
        </div>
      )}

      <nav className="pos-categories" role="tablist" aria-label="Filtrar por categoría">
        <button
          type="button"
          role="tab"
          aria-selected={activeCategory === null}
          className={`pos-chip ${activeCategory === null ? 'is-active' : ''}`}
          onClick={() => setActiveCategory(null)}
        >
          Todos
          <span className="pos-chip__count">{filteredMenu.length}</span>
        </button>
        {categories.map((category) => (
          <button
            key={category.id}
            type="button"
            role="tab"
            aria-selected={activeCategory === category.id}
            className={`pos-chip ${activeCategory === category.id ? 'is-active' : ''}`}
            onClick={() => setActiveCategory(category.id)}
          >
            {category.name}
          </button>
        ))}
      </nav>

      {/* Scrollable product grid */}
      <div className="pos-catalog__scroll">
        {loading ? (
          <div className="pos-skeleton-grid">
            {Array.from({ length: 12 }, (_, i) => (
              <div key={i} className="pos-skeleton-card">
                <div className="pos-skeleton-card__media" />
                <div className="pos-skeleton-card__line" />
                <div className="pos-skeleton-card__line pos-skeleton-card__line--short" />
              </div>
            ))}
          </div>
        ) : loadError ? (
          <div className="pos-state is-error">
            <p>{loadError}</p>
          </div>
        ) : filteredMenu.length === 0 ? (
          <div className="pos-state">
            <i
              className="ph ph-package-search"
              aria-hidden="true"
              style={{ fontSize: '2.25rem' }}
            />
            <p>{searchQuery ? 'Sin resultados' : 'Catálogo vacío'}</p>
            <span>
              {searchQuery
                ? 'Prueba con otro nombre, SKU o código de barras.'
                : 'No hay productos disponibles en este almacén.'}
            </span>
            {searchQuery && (
              <button
                type="button"
                className="pos-state__action-btn"
                onClick={() => setSearchQuery('')}
              >
                Limpiar búsqueda
              </button>
            )}
          </div>
        ) : (
          <div className="pos-grid">
            {filteredMenu.map((item) => {
              const effectiveStock = getEffectiveStock(item);
              const inCart = getItemQuantity(item.id);
              const displayStock =
                effectiveStock !== undefined ? Math.max(0, effectiveStock - inCart) : undefined;
              return (
                <POSItem
                  key={item.id}
                  item={item}
                  onAdd={onAddToCart}
                  quantity={inCart}
                  stockOverride={displayStock}
                  enforceStockCheck={enforceStockCheck}
                />
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}
