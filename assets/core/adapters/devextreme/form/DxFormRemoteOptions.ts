import type { DataRecord } from '@core/data/DataRecord';
import type { FormRemoteOptions } from '@core/form/FormRemoteOptions';

export function getDxRemoteOptions(remoteOptions: FormRemoteOptions, store: unknown): DataRecord {
  return {
    dataSource: {
      store,
      paginate: true,
      pageSize: 10,
    },
    dropDownOptions: {
      container: 'body',
      wrapperAttr: { class: 'crud-dialog-editor-overlay' },
    },
    valueExpr: remoteOptions.valueField,
    displayExpr: remoteOptions.textField,
    searchEnabled: remoteOptions.searchEnabled,
    searchExpr: remoteOptions.searchExpr,
    wrapItemText: true,
    selectionMode: remoteOptions.multiple ? 'multiple' : 'single',
    buttons: ['clear', 'dropDown'],
  };
}
