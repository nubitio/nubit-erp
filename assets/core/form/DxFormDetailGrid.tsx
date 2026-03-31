import React, { MutableRefObject } from 'react';
import DataGrid, {
  DataGridRef,
  Editing,
  KeyboardNavigation,
  LoadPanel,
  Paging,
} from 'devextreme-react/data-grid';
import DataSource from 'devextreme/data/data_source';
import { buildColumns } from '../datagrid/DxDataGridUtils';
import { Field } from '../field/Field';
import { FORM_EVENTS } from './DxForm';
import { buildEmptyRow } from './DxFormDataTransform';

type FormRecord = Record<string, unknown>;
type DetailGridEvent = { component: { saveEditData: () => void }; data: FormRecord };

export interface DxFormDetailGridProps {
  gridRef: MutableRefObject<DataGridRef | null>;
  dataSource: DataSource;
  detailFields: Field[];
  detailIdField: string;
  noDataText: string;
  allowAdding?: boolean;
  allowUpdating?: boolean;
  allowDeleting?: boolean;
  emit: <T>(name: string, payload?: T) => void;
}

export const DxFormDetailGrid = React.memo<DxFormDetailGridProps>(
  ({
    gridRef,
    dataSource,
    detailFields,
    detailIdField,
    noDataText,
    allowAdding = true,
    allowUpdating = true,
    allowDeleting = true,
    emit,
  }) => (
    <DataGrid
      id="form-detail-grid"
      className="dx-form-detail-grid"
      ref={gridRef}
      keyExpr={detailIdField}
      dataSource={dataSource}
      noDataText={noDataText}
      showBorders={true}
      showRowLines={true}
      showColumnLines={true}
      focusedRowEnabled={true}
      hoverStateEnabled={true}
      repaintChangesOnly={true}
      onInitNewRow={(e: DetailGridEvent) => {
        e.data = buildEmptyRow(detailFields);
        e.component.saveEditData();
      }}
      onRowInserted={(e: DetailGridEvent) => {
        e.component.saveEditData();
        emit(FORM_EVENTS.ROW_ADDED, e.data);
      }}
      onRowRemoved={(e: DetailGridEvent) => {
        e.component.saveEditData();
        emit(FORM_EVENTS.ROW_REMOVED, e.data);
      }}
      onRowUpdated={(e: DetailGridEvent) => {
        e.component.saveEditData();
        emit(FORM_EVENTS.ROW_UPDATED, e.data);
      }}
    >
      <LoadPanel showPane={false} />
      <KeyboardNavigation editOnKeyPress={true} enterKeyAction="moveFocus" enterKeyDirection="column" />
      <Editing
        mode="cell"
        allowUpdating={allowUpdating}
        allowDeleting={allowDeleting}
        allowAdding={allowAdding}
        startEditAction="click"
        confirmDelete={false}
      />
      <Paging enabled={false} />
      {buildColumns(detailFields, true)}
    </DataGrid>
  ),
);

DxFormDetailGrid.displayName = 'DxFormDetailGrid';
