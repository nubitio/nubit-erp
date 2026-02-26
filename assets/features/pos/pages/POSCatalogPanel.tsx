import React from 'react';
import { ArrowLeft, PackageSearch, Search, ShoppingCart } from 'lucide-react';
import { POSItem } from './POSItem';
import type { Category, PosItem } from './types';

interface POSCatalogPanelProps {
  loading: boolean;
  loadError: string | null;
  searchQuery: string;
  setSearchQuery: (value: string) => void;
  cartItemsCount: number;
  selectedCategoryLabel?: string;
  filteredMenu: PosItem[];
  lastSaleCode: string | null;
  categories: Category[];
  activeCategory: string | null;
  setActiveCategory: (value: string | null) => void;
  onAddToCart: (item: PosItem) => void | Promise<void>;
  onRemoveFromCart: (id: string) => void;
  getItemQuantity: (id: string) => number;
  getEffectiveStock: (item: PosItem) => number | undefined;
  enforceStockCheck: boolean;
}

export function POSCatalogPanel({
  loading,
  loadError,
  searchQuery,
  setSearchQuery,
  cartItemsCount,
  selectedCategoryLabel,
  filteredMenu,
  lastSaleCode,
  categories,
  activeCategory,
  setActiveCategory,
  onAddToCart,
  onRemoveFromCart,
  getItemQuantity,
  getEffectiveStock,
  enforceStockCheck,
}: POSCatalogPanelProps) {
  return (
    <section className="pos-catalog">
      <header className="pos-toolbar">
        <button
          type="button"
          className="pos-toolbar__back"
          onClick={() => window.history.back()}
          aria-label="Volver"
        >
          <ArrowLeft size={20} />
        </button>

        <div className="pos-toolbar__search">
          <Search size={18} />
          <input
            type="search"
            placeholder="Buscar por nombre, SKU o código..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>

        <div className="pos-toolbar__pill">
          <ShoppingCart size={16} />
          <span>{cartItemsCount} items</span>
        </div>
      </header>

      <div className="pos-catalog__header">
        <div>
          <h1>Punto de venta</h1>
          <p>
            {selectedCategoryLabel || 'Todos'} · {filteredMenu.length} productos
          </p>
        </div>
        {lastSaleCode && <div className="pos-last-sale">Última venta: {lastSaleCode}</div>}
      </div>

      <div className="pos-categories" role="tablist" aria-label="Categorías">
        <button
          type="button"
          className={`pos-chip ${activeCategory === null ? 'is-active' : ''}`}
          onClick={() => setActiveCategory(null)}
        >
          Todos
        </button>
        {categories.map((category) => (
          <button
            key={category.id}
            type="button"
            className={`pos-chip ${activeCategory === category.id ? 'is-active' : ''}`}
            onClick={() => setActiveCategory(category.id)}
          >
            {category.name}
          </button>
        ))}
      </div>

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
          <PackageSearch size={28} />
          <p>No hay productos para ese filtro</p>
        </div>
      ) : (
        <div className="pos-grid">
          {filteredMenu.map((item) => (
            <POSItem
              key={item.id}
              item={item}
              onAdd={onAddToCart}
              onRemove={onRemoveFromCart}
              quantity={getItemQuantity(item.id)}
              stockOverride={getEffectiveStock(item)}
              enforceStockCheck={enforceStockCheck}
            />
          ))}
        </div>
      )}
    </section>
  );
}
