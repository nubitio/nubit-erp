import { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import { DataGrid } from 'devextreme-react';
import { Column, type DataGridRef, Editing, Scrolling } from 'devextreme-react/data-grid';
import type { RowUpdatedEvent } from 'devextreme/ui/data_grid';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';
import NumberUtils from '@shared/lib/NumberUtils';
import type { GridCellContext } from '@core/field/FieldCallbacks';

export type PriceEditorRow = {
  id: string | number;
  name?: string;
  price?: number | string;
  updated?: boolean;
};

export interface PriceEditorGridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  setRows: (rows: PriceEditorRow[]) => void;
  getUpdatedRows: () => PriceEditorRow[];
}

export const PriceEditorGrid = forwardRef<PriceEditorGridHandle>((_props, ref) => {
  const gridRef = useRef<DataGridRef | null>(null);
  const [dataSource] = useState(
    () => new DataSource({ store: new ArrayStore({ key: 'id', data: [] }) }),
  );

  useImperativeHandle(ref, () => ({
    showLoading: (message) => gridRef.current?.instance().beginCustomLoading(message ?? ''),
    hideLoading: () => gridRef.current?.instance().endCustomLoading(),
    setRows: (rows) => {
      const store = dataSource.store() as ArrayStore;
      store.clear();
      rows.forEach((row) => store.insert(row));
      dataSource.reload();
    },
    getUpdatedRows: () => {
      const rows = gridRef.current?.instance().getDataSource().items() ?? [];
      return rows.filter((row: PriceEditorRow) => row.updated);
    },
  }));

  return (
    <DataGrid
      ref={gridRef}
      dataSource={dataSource}
      height="100%"
      showBorders
      showRowLines
      onRowUpdated={(e: RowUpdatedEvent) => {
        e.data.updated = true;
        e.component.saveEditData();
      }}
    >
      <Editing mode="cell" allowUpdating={true} />
      <Scrolling mode="virtual" />
      <Column dataField="id" caption="ID" visible={false} />
      <Column dataField="name" caption="Producto" minWidth={300} allowEditing={false} />
      <Column
        dataField="price"
        caption="Precio"
        alignment="right"
        dataType="number"
        cellRender={(cell: GridCellContext) => NumberUtils.formatCurrency(cell.value)}
      />
      <Column dataField="updated" caption="" visible={false} />
    </DataGrid>
  );
});
