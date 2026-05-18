import type { FormRemoteOptions } from '@core/form/FormRemoteOptions';
import { dxUtils } from '@core/adapters/devextreme/data/DxUtils';

export function getDxRemoteStore(remoteOptions: FormRemoteOptions): unknown {
  return dxUtils.getDataStore(
    remoteOptions.url,
    remoteOptions.valueField,
    remoteOptions.byKeyUrl,
    remoteOptions.filters,
    [],
    remoteOptions.loadOptions,
    remoteOptions.iriMode,
  );
}
