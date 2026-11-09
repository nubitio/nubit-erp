import { identityField, textField, type Field } from '@nubitio/crud';

export const RoleFields = () => {
  const fields: Field[] = [];

  fields.push(identityField().valueType('number').build());
  fields.push(
    textField()
      .name('name')
      .label('Descripción')
      .required(true)
      // The grid adds a separate quick-search box for the first text column that has no formatter.
      // The column's own header filter already searches the description, so give it one (it just
      // renders the value) to avoid a second, redundant search field above the grid.
      .formatter((cell) => String(cell.value ?? ''))
      .build(),
  );

  return fields;
};
