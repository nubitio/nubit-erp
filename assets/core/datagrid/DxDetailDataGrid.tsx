import { DataGrid } from 'devextreme-react';
import { dxUtils } from '../../shared/data/DxUtils';
import CustomStore from 'devextreme/data/custom_store';
import { useEffect, useMemo, useState } from 'react';
import { buildColumns, getIdField } from './DxDataGridUtils';
import { LoadPanel, Paging, Sorting } from 'devextreme-react/data-grid';
import { Field } from '../field/Field';

type DxDetailDataGridProps = {
  options: {
    url: string | undefined;
    fields: Field[] | undefined;
  };
};

export const DxDetailDataGrid = ({ options }: DxDetailDataGridProps) => {
  const [data, setData] = useState<CustomStore | null>(null);

  // Bug B fix: stabilise `fields` reference so the useEffect below does not
  // re-fire on every parent render (options.fields is a new array each time).
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const fields = useMemo(() => options.fields ?? [], [options.fields]);

  useEffect(() => {
    // Bug A fix: only build the data store when both url and fields are truthy.
    if (!options.url) return;
    const idField = getIdField(fields) || 'id';
    // eslint-disable-next-line react-hooks/set-state-in-effect -- updating DataStore when URL changes is intentional data-source sync
    setData(dxUtils.getDataStore(options.url, idField));
    // fields do not affect the store (only columns) — depend only on url.
  }, [options.url]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="grid-detail">
      <DataGrid
        dataSource={data}
        className="grid"
        noDataText=""
        columnMinWidth={50}
        columnAutoWidth={true}
      >
        <LoadPanel enabled />
        <Sorting mode="multiple" />
        {buildColumns(fields)}
        <Paging enabled={false} />
      </DataGrid>
    </div>
  );
};
