import { Field } from '@platform/field/Field';
import { FieldBuilder } from '@platform/field/FieldBuilder';
import { FieldType } from '@platform/field/FieldType';
import { Constants } from '@platform/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const VoucherSeriesFields = () => {
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
      .type(FieldType.ENTITY)
      .name('branch')
      .label('Local')
      .url(Constants.API_BASE_URL + 'branches')
      .valueField('id')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('voucherType')
      .label('Tipo comprobante')
      .url(Constants.API_BASE_URL + 'voucher-types')
      .valueField('code')
      .textField('name')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder().type(FieldType.TEXT).name('series').label('Serie').required(true).build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('associatedSeries')
      .label('Serie asociada')
      .build(),
  );
  return fields;
};
