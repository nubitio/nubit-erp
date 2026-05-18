import type { DataRecord } from '@core/data/DataRecord';
import type { FormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';

export function getDxBasicEditorOptions(options: FormSimpleEditorOptions): DataRecord {
  return {
    ...(options.select
      ? {
          dataSource: options.select.dataSource,
          valueExpr: options.select.valueExpr,
          displayExpr: options.select.displayExpr,
          buttons: ['dropDown'],
        }
      : {}),
    ...(options.numberFormat ? { format: options.numberFormat } : {}),
    ...(options.checkboxText ? { text: options.checkboxText } : {}),
    ...(options.date
      ? {
          type: options.date.type,
          displayFormat: options.date.displayFormat,
          defaultValue: options.date.defaultValue,
          min: options.date.min,
        }
      : {}),
    ...(options.html
      ? {
          height: options.html.height,
          value: options.html.value,
          toolbar: {
            items: options.html.toolbarItems,
          },
        }
      : {}),
    ...(options.switchHint ? { hint: options.switchHint } : {}),
  };
}
