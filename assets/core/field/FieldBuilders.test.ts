import { describe, it, expect } from 'vitest';
import {
  identityField,
  textField,
  entityField,
  enumField,
  currencyField,
  switchField,
  numberField,
  textareaField,
  dateField,
  datetimeField,
  noneField,
  selectField,
  checkboxField,
  passwordField,
  imageField,
} from './FieldBuilders';
import { FieldType } from './FieldType';

// ---------------------------------------------------------------------------
// identityField
// ---------------------------------------------------------------------------

describe('FieldBuilders › identityField', () => {
  it('build() returns the correct identity field defaults', () => {
    const field = identityField().build();

    expect(field.isIdentity).toBe(true);
    expect(field.visible).toBe(false);
    expect(field.required).toBe(true);
    expect(field.name).toBe('id');
    expect(field.type).toBe(FieldType.NONE);
  });

  it('label defaults to "Id"', () => {
    const field = identityField().build();
    expect(field.label).toBe('Id');
  });

  it('fluent override of required is respected', () => {
    const field = identityField().required(false).build();
    expect(field.required).toBe(false);
  });

  it('fluent override of name is respected', () => {
    const field = identityField().name('uuid').build();
    expect(field.name).toBe('uuid');
  });
});

// ---------------------------------------------------------------------------
// textField
// ---------------------------------------------------------------------------

describe('FieldBuilders › textField', () => {
  it('type is TEXT', () => {
    expect(textField().build().type).toBe(FieldType.TEXT);
  });

  it('.name() sets the name', () => {
    const field = textField().name('businessName').build();
    expect(field.name).toBe('businessName');
  });

  it('.label() sets the label', () => {
    const field = textField().label('Razón social').build();
    expect(field.label).toBe('Razón social');
  });

  it('.required(true) sets required flag', () => {
    const field = textField().required(true).build();
    expect(field.required).toBe(true);
  });

  it('fluent chain .name().label().required().build() produces correct object', () => {
    const field = textField().name('code').label('Código').required(true).build();

    expect(field.name).toBe('code');
    expect(field.label).toBe('Código');
    expect(field.required).toBe(true);
    expect(field.type).toBe(FieldType.TEXT);
  });
});

// ---------------------------------------------------------------------------
// entityField
// ---------------------------------------------------------------------------

describe('FieldBuilders › entityField', () => {
  it('type is ENTITY', () => {
    const field = entityField('/api/customers', 'id', 'businessName').build();
    expect(field.type).toBe(FieldType.ENTITY);
  });

  it('url is set from constructor argument', () => {
    const field = entityField('/api/customers', 'id', 'businessName').build();
    expect(field.url).toBe('/api/customers');
  });

  it('valueField is set from constructor argument', () => {
    const field = entityField('/api/customers', 'id', 'businessName').build();
    expect(field.valueField).toBe('id');
  });

  it('textField is set from constructor argument', () => {
    const field = entityField('/api/customers', 'id', 'businessName').build();
    expect(field.textField).toBe('businessName');
  });

  it('fluent chain for entity field works correctly', () => {
    const field = entityField('/api/customers', 'id', 'businessName')
      .name('customer')
      .label('Cliente')
      .required(true)
      .build();

    expect(field.name).toBe('customer');
    expect(field.label).toBe('Cliente');
    expect(field.required).toBe(true);
    expect(field.type).toBe(FieldType.ENTITY);
    expect(field.url).toBe('/api/customers');
  });

  it('.multiple(true) sets the multiple flag', () => {
    const field = entityField('/api/tags', 'id', 'name').multiple(true).build();
    expect(field.multiple).toBe(true);
  });
});

// ---------------------------------------------------------------------------
// enumField
// ---------------------------------------------------------------------------

describe('FieldBuilders › enumField', () => {
  const options = [
    { value: 'income', text: 'Ingreso' },
    { value: 'expense', text: 'Egreso' },
  ];

  it('type is ENUM', () => {
    expect(enumField(options).build().type).toBe(FieldType.ENUM);
  });

  it('data array is set from constructor argument', () => {
    const field = enumField(options).build();
    expect(field.data).toEqual(options);
  });

  it('data array length matches the options provided', () => {
    const field = enumField(options).build();
    expect(field.data).toHaveLength(2);
  });

  it('empty options array results in empty data', () => {
    const field = enumField([]).build();
    expect(field.data).toEqual([]);
  });
});

// ---------------------------------------------------------------------------
// currencyField
// ---------------------------------------------------------------------------

describe('FieldBuilders › currencyField', () => {
  it('type is CURRENCY', () => {
    expect(currencyField().build().type).toBe(FieldType.CURRENCY);
  });

  it('sendAsString defaults to true', () => {
    expect(currencyField().build().sendAsString).toBe(true);
  });

  it('align defaults to "right"', () => {
    expect(currencyField().build().align).toBe('right');
  });

  it('fluent chain works correctly', () => {
    const field = currencyField().name('grandTotal').label('Total').precision(2).build();

    expect(field.name).toBe('grandTotal');
    expect(field.label).toBe('Total');
    expect(field.precision).toBe(2);
    expect(field.sendAsString).toBe(true);
    expect(field.align).toBe('right');
  });
});

// ---------------------------------------------------------------------------
// switchField
// ---------------------------------------------------------------------------

describe('FieldBuilders › switchField', () => {
  it('type is SWITCH', () => {
    expect(switchField().build().type).toBe(FieldType.SWITCH);
  });

  it('.defaultValue(true) sets defaultValue', () => {
    const field = switchField().defaultValue(true).build();
    expect(field.defaultValue).toBe(true);
  });
});

// ---------------------------------------------------------------------------
// numberField
// ---------------------------------------------------------------------------

describe('FieldBuilders › numberField', () => {
  it('type is NUMBER', () => {
    expect(numberField().build().type).toBe(FieldType.NUMBER);
  });

  it('sendAsString defaults to false', () => {
    expect(numberField().build().sendAsString).toBe(false);
  });

  it('.precision() sets the precision', () => {
    const field = numberField().precision(4).build();
    expect(field.precision).toBe(4);
  });
});

// ---------------------------------------------------------------------------
// textareaField
// ---------------------------------------------------------------------------

describe('FieldBuilders › textareaField', () => {
  it('type is TEXTAREA', () => {
    expect(textareaField().build().type).toBe(FieldType.TEXTAREA);
  });

  it('fluent chain .name().label().build() works', () => {
    const field = textareaField().name('note').label('Nota').build();
    expect(field.name).toBe('note');
    expect(field.label).toBe('Nota');
  });
});

// ---------------------------------------------------------------------------
// BaseFieldBuilder defaults (via textField as the simplest concrete builder)
// ---------------------------------------------------------------------------

describe('FieldBuilders › BaseFieldBuilder defaults', () => {
  it('sortable defaults to true', () => {
    expect(textField().build().sortable).toBe(true);
  });

  it('filterable defaults to true', () => {
    expect(textField().build().filterable).toBe(true);
  });

  it('hideable defaults to true', () => {
    expect(textField().build().hideable).toBe(true);
  });

  it('visible defaults to true', () => {
    expect(textField().build().visible).toBe(true);
  });

  it('required defaults to false', () => {
    expect(textField().build().required).toBe(false);
  });

  it('isIdentity defaults to false', () => {
    expect(textField().build().isIdentity).toBe(false);
  });

  it('multiple defaults to false', () => {
    expect(textField().build().multiple).toBe(false);
  });

  it('readonly defaults to false', () => {
    expect(textField().build().readonly).toBe(false);
  });

  it('disabled defaults to false', () => {
    expect(textField().build().disabled).toBe(false);
  });

  it('col defaults to 12', () => {
    expect(textField().build().col).toBe(12);
  });

  it('align defaults to "left"', () => {
    expect(textField().build().align).toBe('left');
  });

  it('validators defaults to empty array', () => {
    expect(textField().build().validators).toEqual([]);
  });

  it('mode defaults to "local"', () => {
    expect(textField().build().mode).toBe('local');
  });

  it('searchEnabled defaults to true', () => {
    expect(textField().build().searchEnabled).toBe(true);
  });

  it('visibleOnForm defaults to true', () => {
    expect(textField().build().visibleOnForm).toBe(true);
  });

  it('.sortable(false) overrides the default', () => {
    const field = textField().sortable(false).build();
    expect(field.sortable).toBe(false);
  });

  it('.filterable(false) overrides the default', () => {
    const field = textField().filterable(false).build();
    expect(field.filterable).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// dateField
// ---------------------------------------------------------------------------

describe('FieldBuilders › dateField', () => {
  it('type is DATE', () => {
    expect(dateField().build().type).toBe(FieldType.DATE);
  });

  it('defaultValue is set to a Date instance', () => {
    const field = dateField().build();
    expect(field.defaultValue).toBeInstanceOf(Date);
  });
});

// ---------------------------------------------------------------------------
// datetimeField
// ---------------------------------------------------------------------------

describe('FieldBuilders › datetimeField', () => {
  it('type is DATETIME', () => {
    expect(datetimeField().build().type).toBe(FieldType.DATETIME);
  });

  it('defaultValue is set to a Date instance', () => {
    const field = datetimeField().build();
    expect(field.defaultValue).toBeInstanceOf(Date);
  });
});

// ---------------------------------------------------------------------------
// noneField (plain, non-identity)
// ---------------------------------------------------------------------------

describe('FieldBuilders › noneField', () => {
  it('type is NONE', () => {
    expect(noneField().build().type).toBe(FieldType.NONE);
  });

  it('isIdentity defaults to false', () => {
    expect(noneField().build().isIdentity).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// selectField
// ---------------------------------------------------------------------------

describe('FieldBuilders › selectField', () => {
  it('type is SELECT', () => {
    expect(selectField().build().type).toBe(FieldType.SELECT);
  });
});

// ---------------------------------------------------------------------------
// checkboxField
// ---------------------------------------------------------------------------

describe('FieldBuilders › checkboxField', () => {
  it('type is CHECKBOX', () => {
    expect(checkboxField().build().type).toBe(FieldType.CHECKBOX);
  });
});

// ---------------------------------------------------------------------------
// passwordField
// ---------------------------------------------------------------------------

describe('FieldBuilders › passwordField', () => {
  it('type is PASSWORD', () => {
    expect(passwordField().build().type).toBe(FieldType.PASSWORD);
  });
});

// ---------------------------------------------------------------------------
// imageField
// ---------------------------------------------------------------------------

describe('FieldBuilders › imageField', () => {
  it('type is FILE', () => {
    const field = imageField('https://api.example.com/').build();
    expect(field.type).toBe(FieldType.FILE);
  });

  it('url is composed from apiBaseUrl + "media"', () => {
    const field = imageField('https://api.example.com/').build();
    expect(field.url).toBe('https://api.example.com/media');
  });

  it('accept defaults to "image/*"', () => {
    const field = imageField('https://api.example.com/').build();
    expect(field.accept).toBe('image/*');
  });

  it('align defaults to "center"', () => {
    const field = imageField('https://api.example.com/').build();
    expect(field.align).toBe('center');
  });
});

// ---------------------------------------------------------------------------
// Fluent chaining — cross-builder verification
// ---------------------------------------------------------------------------

describe('FieldBuilders › fluent chaining correctness', () => {
  it('entityField full chain: url + valueField + textField + name + label + required', () => {
    const field = entityField('/api/products', 'id', 'name')
      .name('product')
      .label('Producto')
      .required(true)
      .col(6)
      .build();

    expect(field.type).toBe(FieldType.ENTITY);
    expect(field.url).toBe('/api/products');
    expect(field.valueField).toBe('id');
    expect(field.textField).toBe('name');
    expect(field.name).toBe('product');
    expect(field.label).toBe('Producto');
    expect(field.required).toBe(true);
    expect(field.col).toBe(6);
  });

  it('currencyField chain with all typical options', () => {
    const fmt = (e: any) => e.value;
    const field = currencyField()
      .name('subtotal')
      .label('Subtotal')
      .precision(4)
      .formatter(fmt)
      .build();

    expect(field.type).toBe(FieldType.CURRENCY);
    expect(field.name).toBe('subtotal');
    expect(field.sendAsString).toBe(true);
    expect(field.align).toBe('right');
    expect(field.precision).toBe(4);
    expect(field.formatter).toBe(fmt);
  });

  it('enumField chain with name, label and data integrity', () => {
    const options = [
      { value: 1, text: 'One' },
      { value: 2, text: 'Two' },
      { value: 3, text: 'Three' },
    ];
    const field = enumField(options).name('status').label('Estado').build();

    expect(field.type).toBe(FieldType.ENUM);
    expect(field.name).toBe('status');
    expect(field.data).toHaveLength(3);
    expect(field.data[0]).toEqual({ value: 1, text: 'One' });
  });
});
