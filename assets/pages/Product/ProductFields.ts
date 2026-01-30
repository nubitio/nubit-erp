import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { Constants } from '../../core/common/Constants';
import NumberUtils from '../../core/utils/NumberUtils';
import { SharedUtils } from '../../core/utils/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const ProductFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.FILE)
      .name('photo')
      .label('Imagen')
      .align('center')
      .url(Constants.API_BASE_URL + 'media')
      .accept('image/*')
      .formatter((e: ColumnCellTemplateData) => {
        return SharedUtils.imageFormatter(e.value?.path, e.data?.descripcion);
      })
      .build(),
  );
  fields.push(new FieldBuilder().type(FieldType.TEXT).name('code').label('Código').build());
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('type')
      .label('Tipo')
      .data([
        { value: 1, text: 'Fisico' },
        { value: 2, text: 'Servicio' },
      ])
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => {
        return cell.value === 1 ? 'Fisico' : 'Servicio';
      })
      .defaultValue(1)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('name')
      .label('Nombre')
      .sortable(true)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('category')
      .label('Categoría')
      .url(Constants.API_BASE_URL + 'categories')
      .valueField('id')
      .textField('name')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('unitOfMeasure')
      .label('Unidad')
      .url(Constants.API_BASE_URL + 'unit-of-measures')
      .valueField('id')
      .textField('name')
      .required(true)
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .valueType('number')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('detractionCatalog')
      .label('Detracción (Bien/Servicio)')
      .url(Constants.API_BASE_URL + 'sunat-catalogs')
      .filters([
        {
          field: 'catalog',
          operator: '=',
          value: 54,
        },
      ])
      .valueField('id')
      .textField('description')
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
    new FieldBuilder()
      .type(FieldType.CURRENCY)
      .name('salePrice')
      .label('Precio')
      .align('right')
      .sendAsString(true)
      .formatter((cell: ColumnCellTemplateData) => NumberUtils.formatCurrency(cell.value))
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('stock')
      .label('Stock')
      .align('center')
      .visibleOnForm(false)
      .build(),
  );

  return fields;
};
