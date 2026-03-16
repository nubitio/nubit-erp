import { Field } from '@platform/field/Field';
import { identityField, entityField, textField } from '@platform/field/FieldBuilders';
import { Constants } from '@platform/common/Constants';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const VoucherSeriesFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().build());
  fields.push(
    entityField(Constants.API_BASE_URL + 'branches', 'id', 'name')
      .name('branch')
      .label('Local')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(
    entityField(Constants.API_BASE_URL + 'voucher-types', 'code', 'name')
      .name('voucherType')
      .label('Tipo comprobante')
      .formatter((cell: ColumnCellTemplateData) => cell.value?.name)
      .required(true)
      .build(),
  );
  fields.push(textField().name('series').label('Serie').required(true).build());
  fields.push(textField().name('associatedSeries').label('Serie asociada').build());
  return fields;
};
