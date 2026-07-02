import { forwardRef, useImperativeHandle, useMemo, useRef, useState } from 'react';
import {
  DataGridView,
  identityField,
  numberField,
  textField,
  type DataRecord,
  type Field,
  type GridHandle,
} from '@nubitio/crud';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import AppResponse from '@shared/api/AppResponse';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import { priceListEndpoints } from '../api/priceListEndpoints';

export type PriceEditorRow = DataRecord & {
  id: string | number;
  name?: string;
  /** Effective price as decimal string from the API, or number while edited by the grid. */
  price?: string | number;
  priceSource?: 'override' | 'multiplier' | 'base';
};

export interface PriceEditorGridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  setPriceListId: (id: string | number | null) => void;
  setRows: (rows: PriceEditorRow[]) => void;
  hasChanges: () => boolean;
  saveChanges: () => Promise<boolean>;
}

const SOURCE_LABEL: Record<string, string> = {
  override: 'Precio especial',
  multiplier: 'Multiplicador',
  base: 'Precio base',
};

const formatPriceSource = (value: unknown): string => SOURCE_LABEL[String(value)] ?? SOURCE_LABEL.base;

export const PriceEditorGrid = forwardRef<PriceEditorGridHandle>(function PriceEditorGrid(
  _props,
  ref,
) {
  const gridRef = useRef<GridHandle | null>(null);
  const priceListIdRef = useRef<string | number | null>(null);
  const [rows, setRows] = useState<PriceEditorRow[]>([]);

  const fields = useMemo<Field[]>(
    () => [
      identityField().name('id').valueType('number').build(),
      textField()
        .name('name')
        .label('Producto')
        .readonly(true)
        .minWidth(320)
        .build(),
      numberField()
        .name('price')
        .label('Precio')
        .align('right')
        .required(true)
        .precision(4)
        .validators([{ type: 'range', options: { min: 0 } }])
        .formatter((cell) => NumberUtils.formatCurrency(Number(cell.value ?? 0)))
        .build(),
      textField()
        .name('priceSource')
        .label('Origen')
        .readonly(true)
        .width(150)
        .formatter((cell) => formatPriceSource(cell.value))
        .build(),
    ],
    [],
  );

  const saveRows = async (draftRows: DataRecord[]) => {
    const priceListId = priceListIdRef.current;
    if (!priceListId) {
      toast('Debe seleccionar una tarifa', 'warning');
      throw new Error('Missing price list id');
    }

    const pricesPayload = draftRows.map((item) => ({
      productId: item.id,
      price: String(Number(item.price ?? 0).toFixed(4)),
    }));

    const response = await httpRequestData<AppResponse>(
      httpClient.post<AppResponse>(priceListEndpoints.updatePrices(), {
        priceListId,
        prices: pricesPayload,
      }),
    );

    setRows((current) =>
      current.map((row) => {
        const updated = draftRows.find((item) => item.id === row.id);
        return updated
          ? { ...row, price: String(Number(updated.price ?? 0).toFixed(4)), priceSource: 'override' }
          : row;
      }),
    );
    toast(response.message, 'success');
  };

  useImperativeHandle(ref, () => ({
    showLoading: (message) => gridRef.current?.showLoading(message),
    hideLoading: () => gridRef.current?.hideLoading(),
    setPriceListId: (id) => {
      priceListIdRef.current = id;
    },
    setRows: (newRows) => setRows(newRows.map((row) => ({ ...row }))),
    hasChanges: () => gridRef.current?.hasEditData() ?? false,
    saveChanges: () => gridRef.current?.saveChanges() ?? Promise.resolve(false),
  }));

  return (
    <div style={{ height: '55vh', minHeight: 320, display: 'flex', flexDirection: 'column' }}>
      <DataGridView
        ref={gridRef}
        id="price-list-editor"
        title="Precios"
        url={priceListEndpoints.list()}
        data={rows}
        fields={fields}
        editMode="cell"
        inlineEditToolbar={false}
        inlineRowActions={false}
        allowAdd={false}
        allowEdit
        allowDelete={false}
        allowExport={false}
        filterRow={false}
        paging={false}
        toolbarVisible={false}
        onBatchSave={saveRows}
        zebraRows={false}
      />
    </div>
  );
});
