import { DataGrid } from 'devextreme-react';
import { dxUtils } from '../../shared/data/DxUtils';
import CustomStore from 'devextreme/data/custom_store';
import { useEffect, useState } from 'react';
import { buildColumns, getIdField } from './DxDataGridUtils';
import { Paging } from 'devextreme-react/data-grid';
import { Field } from '../field/Field';

type DxDetailDataGridProps = {
  options: {
    url: string | undefined;
    fields: Field[] | undefined;
  };
};

export const DxDetailDataGrid = ({ options }: DxDetailDataGridProps) => {
  const [data, setData] = useState<CustomStore | null>(null);

  const fields = options.fields ?? [];

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- updating DataStore when URL/fields change is intentional data-source sync
    setData(dxUtils.getDataStore(options.url ?? '', getIdField(fields)));
  }, [fields, options.url]);

  return (
    <div className="grid-detail">
      <DataGrid
        dataSource={data}
        className="grid"
        noDataText=""
        columnMinWidth={50}
        columnAutoWidth={true}
      >
        {buildColumns(fields)}
        <Paging enabled={false} />
      </DataGrid>
    </div>
  );
};
