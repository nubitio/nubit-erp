import { ScopeTabs } from '@nubitio/ui';
import type { WarehouseOption } from '@shared/hooks/useWarehouses';

interface InventoryWarehouseTabsProps {
  warehouses: WarehouseOption[];
  selectedWarehouseId: string | null;
  onChange: (warehouseId: string | null) => void;
}

export const InventoryWarehouseTabs = ({
  warehouses,
  selectedWarehouseId,
  onChange,
}: InventoryWarehouseTabsProps) => (
  <ScopeTabs
    testId="inventory-warehouse-tabs"
    density="compact"
    ariaLabel="Almacenes"
    allLabel="Todos"
    options={warehouses.map((warehouse) => ({ key: warehouse.id, label: warehouse.name }))}
    selectedKey={selectedWarehouseId}
    onChange={onChange}
  />
);