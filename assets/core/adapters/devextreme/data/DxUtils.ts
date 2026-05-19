import CustomStore from 'devextreme/data/custom_store';
import { FilterDescriptor, SortDescriptor } from 'devextreme/data';
import { FilterRule } from '@core/field/FilterRule';
import { LoadOption } from '@core/field/Field';
import {
  ApiPlatformRemoteDataSource,
  type RemoteLoadOptions,
} from '@core/data/ApiPlatformRemoteDataSource';

class DxUtils {
  makeFilterRules(filterRules: FilterRule[]): string {
    return new ApiPlatformRemoteDataSource({ url: '', idField: 'id' }).makeFilterRules(filterRules);
  }

  getDataStore(
    url: string,
    idField: string,
    byKeyUrl: string | null = null,
    defaultFilterRules: FilterDescriptor[] = [],
    defaultSortRules: SortDescriptor<any>[] = [],
    options: LoadOption[] = [],
    iriMode: boolean = false,
  ): CustomStore {
    const dataSource = new ApiPlatformRemoteDataSource({
      url,
      idField,
      byKeyUrl,
      defaultFilterRules: defaultFilterRules as unknown[][],
      defaultSortRules: defaultSortRules as unknown as RemoteLoadOptions['sort'],
      options,
      iriMode,
    });

    return new CustomStore({
      key: idField,
      load: (loadOptions) => dataSource.load(loadOptions as RemoteLoadOptions),
      byKey: (key) => dataSource.byKey(key),
    });
  }
}

export const dxUtils = new DxUtils();
