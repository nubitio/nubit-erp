import { forwardRef, useImperativeHandle, useRef, useState } from 'react';
import { Button, DataGrid } from 'devextreme-react';
import { Column, type DataGridRef, Editing, Item, Scrolling, Toolbar } from 'devextreme-react/data-grid';
import DataSource from 'devextreme/data/data_source';
import ArrayStore from 'devextreme/data/array_store';

export type CashClosureRow = {
  id?: string | number;
  paymentMethodId: number;
  paymentMethod?: string;
  expectedAmount: number | string;
  countedAmount?: number;
  difference?: number;
  note?: string;
  updated?: boolean;
};

export interface CashClosureGridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  setRows: (rows: CashClosureRow[]) => void;
  getRows: () => CashClosureRow[];
  getDifferences: () => { paymentMethodId: number; difference: number }[];
}

type CashClosureGridProps = {
  onCloseRequest: () => void;
};

export const CashClosureGrid = forwardRef<CashClosureGridHandle, CashClosureGridProps>(
  ({ onCloseRequest }, ref) => {
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
      getRows: () => gridRef.current?.instance().getDataSource().items() ?? [],
      getDifferences: () => {
        const rows = gridRef.current?.instance().getDataSource().items() ?? [];
        return rows.map((row: CashClosureRow) => ({
          paymentMethodId: row.paymentMethodId,
          difference: Number(row.countedAmount ?? 0) - Number(row.expectedAmount ?? 0),
        }));
      },
    }));

    return (
      <DataGrid
        ref={gridRef}
        className="grid theme-dependent"
        dataSource={dataSource}
        height="100%"
        showBorders={true}
        showRowLines={true}
        onRowUpdated={(e: any) => {
          e.data.updated = true;
          e.component.saveEditData();
        }}
        onRowPrepared={(e: any) => {
          if (e.rowType !== 'data' || e.data == null) return;
          const diff =
            e.data.countedAmount != null ? e.data.countedAmount - e.data.expectedAmount : NaN;
          e.rowElement.classList.remove('row-diff-negative', 'row-diff-positive');
          if (!isNaN(diff) && diff !== 0) {
            e.rowElement.classList.add(diff < 0 ? 'row-diff-negative' : 'row-diff-positive');
          }
        }}
      >
        <Editing mode="cell" allowUpdating={true} />
        <Scrolling mode="virtual" />
        <Column
          dataField="paymentMethod"
          caption="Forma de pago"
          alignment="left"
          dataType="string"
          allowEditing={false}
        />
        <Column
          dataField="expectedAmount"
          caption="Monto esperado"
          alignment="right"
          dataType="number"
          format={{ type: 'currency', precision: 2 }}
          allowEditing={false}
        />
        <Column
          dataField="countedAmount"
          caption="Monto contado"
          alignment="right"
          dataType="number"
          format={{ type: 'currency', precision: 2 }}
          cssClass="col-counted-amount"
          editCellRender={(cell: any) => (
            <div>
              <input
                type="number"
                className="dx-texteditor-input"
                step="0.01"
                defaultValue={cell.value}
                onBlur={(e) => cell.setValue(parseFloat(e.target.value))}
                style={{ width: '100%', textAlign: 'right' }}
              />
              {cell.data.paymentMethodId !== 1 && (
                <small style={{ color: 'var(--texteditor-label-color, #888)', fontSize: '11px' }}>
                  Ingresa el total de tu terminal o app
                </small>
              )}
            </div>
          )}
        />
        <Column
          dataField="difference"
          caption="Diferencia"
          alignment="right"
          dataType="number"
          format={{ type: 'currency', precision: 2 }}
          allowEditing={false}
          calculateCellValue={(row: CashClosureRow) => {
            const diff = Number(row.countedAmount ?? NaN) - Number(row.expectedAmount ?? 0);
            return diff === diff ? diff : '';
          }}
        />
        <Column dataField="note" caption="Nota" minWidth={300} />
        <Column dataField="updated" caption="" visible={false} />
        <Toolbar>
          <Item location="before">
            <div className="grid-header">Cierre de caja</div>
          </Item>
          <Item location="after" locateInMenu="auto">
            <Button
              text="Cerrar caja"
              type="default"
              stylingMode="outlined"
              onClick={onCloseRequest}
              elementAttr={{ 'data-testid': 'cash-close-btn' }}
            />
          </Item>
        </Toolbar>
      </DataGrid>
    );
  },
);
