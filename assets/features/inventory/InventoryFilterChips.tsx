import { FilterPanel } from '@nubitio/ui';
import type { InventoryCategoryOption } from './useInventoryCategories';

export type StockStatusFilter = 'all' | 'low' | 'out' | 'ok';

interface InventoryFilterChipsProps {
  categories: InventoryCategoryOption[];
  selectedCategoryId: string | null;
  onCategoryChange: (categoryId: string | null) => void;
  selectedStatus: StockStatusFilter;
  onStatusChange: (status: StockStatusFilter) => void;
}

const STATUS_OPTIONS: { key: StockStatusFilter; label: string }[] = [
  { key: 'all', label: 'Todos' },
  { key: 'low', label: 'Bajo mínimo' },
  { key: 'out', label: 'Sin stock' },
  { key: 'ok', label: 'OK' },
];

export const InventoryFilterChips = ({
  categories,
  selectedCategoryId,
  onCategoryChange,
  selectedStatus,
  onStatusChange,
}: InventoryFilterChipsProps) => (
  <FilterPanel
    testId="inventory-filter-chips"
    layout="toolbar"
    density="compact"
    category={{
      title: 'Categoría',
      options: categories.map((category) => ({ key: category.id, label: category.name })),
      selectedKey: selectedCategoryId,
      onSelect: onCategoryChange,
      allLabel: 'Todas',
      searchPlaceholder: 'Buscar categoría…',
      emptyMessage: 'Sin coincidencias',
      ariaLabel: 'Categorías',
    }}
    status={{
      title: 'Estado',
      options: STATUS_OPTIONS.map((option) => ({ value: option.key, label: option.label })),
      value: selectedStatus,
      onChange: onStatusChange,
      ariaLabel: 'Estado de stock',
    }}
  />
);