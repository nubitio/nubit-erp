import { Constants } from '@core/common/Constants';
import { Field } from '@core/field/Field';
import {
  identityField,
  entityField,
  currencyField,
  imageField,
  enumField,
  textField,
  noneField,
} from '@core/field/FieldBuilders';
import NumberUtils from '@shared/lib/NumberUtils';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const ProductFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    imageField(Constants.API_BASE_URL)
      .name('photo')
      .label('Imagen')
      .formatter((e: ColumnCellTemplateData) => {
        return SharedUtils.imageFormatter(e.value?.path, e.data?.name ?? e.data?.descripcion);
      })
      .build(),
  );
  fields.push(textField().name('code').label('Código').build());
  fields.push(textField().name('sku').label('SKU').build());
  fields.push(textField().name('barcode').label('Código de barras').build());
  fields.push(
    enumField([
      { value: 'GOOD', text: 'Bien físico' },
      { value: 'SERVICE', text: 'Servicio' },
      { value: 'DIGITAL', text: 'Digital' },
    ])
      .name('productKind')
      .label('Tipo de producto')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => {
        const map: Record<string, string> = {
          GOOD: 'Bien físico',
          SERVICE: 'Servicio',
          DIGITAL: 'Digital',
        };
        return map[cell.value] ?? cell.value;
      })
      .defaultValue('GOOD')
      .build(),
  );
  fields.push(
    enumField([
      { value: 'TRACKED', text: 'Con stock (trazable)' },
      { value: 'NOT_TRACKED', text: 'Sin stock' },
      { value: 'COMPOSITE', text: 'Compuesto (BOM)' },
    ])
      .name('inventoryBehavior')
      .label('Control de stock')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => {
        const map: Record<string, string> = {
          TRACKED: 'Con stock',
          NOT_TRACKED: 'Sin stock',
          COMPOSITE: 'Compuesto',
        };
        return map[cell.value] ?? cell.value;
      })
      .defaultValue('TRACKED')
      .visibleOnForm(true)
      .build(),
  );
  // Keep legacy type field hidden but still sent for backward compat
  fields.push(
    enumField([
      { value: 1, text: 'Fisico' },
      { value: 2, text: 'Servicio' },
    ])
      .name('type')
      .label('Tipo (legacy)')
      .required(false)
      .hidden(true)
      .visible(false)
      .defaultValue(1)
      .build(),
  );
  fields.push(textField().name('name').label('Nombre').sortable(true).required(true).build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'categories', 'id', 'name')
      .name('category')
      .label('Categoría')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'unit-of-measures', 'id', 'name')
      .name('unitOfMeasure')
      .label('Unidad')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .valueType('number')
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'sunat-catalogs', 'id', 'description')
      .name('detractionCatalog')
      .label('Detracción (Bien/Servicio)')
      .filters([
        {
          field: 'catalog',
          operator: '=',
          value: 54,
        },
      ])
      .itemFormatter((item: any) => {
        if (!item) {
          return '';
        }
        const percent = item.percentage !== null ? ` ${item.percentage}%` : '';
        return `${item.code} - ${item.description}${percent}`;
      })
      .formatter((cell: ColumnCellTemplateData) => {
        const item = cell.value ?? cell.data?.detractionCatalog;
        if (!item) {
          return '';
        }
        if (typeof item === 'string') {
          return item;
        }
        const percent = item.percentage !== null ? ` ${item.percentage}%` : '';
        if (!item.code && !item.description) {
          return '';
        }
        return `${item.code ?? ''} - ${item.description ?? ''}${percent}`.trim();
      })
      .build(),
  );
  fields.push(
    currencyField()
      .name('salePrice')
      .label('Precio')
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .required(true)
      .build(),
  );
  fields.push(
    noneField().name('stock').label('Stock').align('center').visibleOnForm(false).build(),
  );

  return fields;
};
