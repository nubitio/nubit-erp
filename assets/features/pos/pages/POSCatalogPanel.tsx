import React, { useEffect } from 'react';
import { PackageSearch, Search } from 'lucide-react';
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
  searchInputRef: React.RefObject<HTMLInputElement>;
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
}: POSCatalogPanelProps) {

  // F2 or '/' to focus search; Escape to clear
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if (
        (e.key === 'F2' || e.key === '/') &&
        document.activeElement !== searchInputRef.current
      ) {
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
          <Search size={14} />
          <input
            ref={searchInputRef}
            type="search"
            placeholder="Buscar producto… (F2)"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            autoComplete="off"
            autoFocus
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
          <div className="pos-state">
            <p>Cargando catálogo...</p>
          </div>
        ) : loadError ? (
          <div className="pos-state is-error">
            <p>{loadError}</p>
          </div>
        ) : filteredMenu.length === 0 ? (
          <div className="pos-state">
            <PackageSearch size={32} />
            <p>Sin resultados</p>
            <span>Prueba con otro nombre, SKU o categoría.</span>
          </div>
        ) : (
          <div className="pos-grid">
            {filteredMenu.map((item) => (
              <POSItem
                key={item.id}
                item={item}
                onAdd={onAddToCart}
                quantity={getItemQuantity(item.id)}
                stockOverride={getEffectiveStock(item)}
                enforceStockCheck={enforceStockCheck}
              />
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
