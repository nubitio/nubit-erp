import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';

export function createDxDetailDataSource(identityField: string): DataSource {
  return new DataSource({
    store: new ArrayStore({
      key: identityField,
      data: [],
    }),
  });
}
