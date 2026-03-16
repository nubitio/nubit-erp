import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';
import React from 'react';

/**
 * Callback for grid column `cellRender` — renders a cell in a DxDataGrid column.
 * Receives the full DevExtreme cell template data object.
 *
 * @example
 *   .formatter(cell => cell.value?.name)
 *   .formatter(cell => NumberUtils.formatCurrency(cell.value))
 */
export type FormatterFn = (cell: ColumnCellTemplateData) => React.ReactNode;

/**
 * Callback for grid column `setCellValue` — called when the user edits a cell in a
 * DxDataGrid inline editor.  DevExtreme passes three arguments:
 *   - `newData`       – partial row data object to mutate with the new value
 *   - `value`         – the new cell value
 *   - `currentRowData`– the full row data before the edit
 *
 * @example
 *   .onChange(function(row, value, currentRow) { row.total = value * currentRow.qty; })
 */
export type GridOnChangeFn = (
  newData: Record<string, unknown>,
  value: unknown,
  currentRowData: Record<string, unknown>,
) => void | Promise<void>;

/**
 * Callback for form field `onValueChanged` — called when the user changes a form
 * field value.  The form infrastructure unwraps the DevExtreme event and passes the
 * raw value directly.
 *
 * @example
 *   .onChange(value => { selectedWarehouseId = value as number; })
 */
export type FormOnChangeFn = (value: unknown) => void | Promise<void>;

/** Union accepted by `.onChange()` — covers both grid and form contexts. */
export type OnChangeFn = GridOnChangeFn | FormOnChangeFn;

/**
 * Callback for `itemRender` in dropdown/select editors — renders a single list item.
 *
 * @example
 *   .itemFormatter(item => `${item.code} – ${item.name}`)
 */
export type ItemFormatterFn = (item: unknown) => React.ReactNode;
