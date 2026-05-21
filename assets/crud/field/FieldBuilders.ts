import { BaseFieldBuilder } from './BaseFieldBuilder';
import { FieldType } from './FieldType';
import type { Field } from './Field';

/**
 * Factory for the standard hidden identity field (type NONE, name 'id').
 *
 * Usage:
 *   identityField().build()
 *   identityField().required(true).build()
 *   identityField().valueType('number').build()
 *
 * Defaults set automatically:
 *   isIdentity(true), visible(false), required(true), name('id'), label('Id')
 */
class IdentityFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.NONE);
    this._field.isIdentity = true;
    this._field.visible = false;
    this._field.required = true;
    this._field.name = 'id';
    this._field.label = 'Id';
  }
}

export function identityField(): IdentityFieldBuilder {
  return new IdentityFieldBuilder();
}

/**
 * Builder for ENTITY (remote select) fields.
 *
 * Required constructor args: url, valueField, textField — the three properties
 * that are ALWAYS present on every ENTITY field in the codebase.
 *
 * Usage:
 *   new EntityFieldBuilder('customers', 'id', 'businessName')
 *     .label('Cliente')
 *     .name('customer')
 *     .mode('remote')
 *     .formatter(cell => cell.value?.businessName)
 *     .build()
 */
export class EntityFieldBuilder extends BaseFieldBuilder {
  constructor(url: string, valueField: string, textField: string) {
    super(FieldType.ENTITY);
    this._field.url = url;
    this._field.valueField = valueField;
    this._field.textField = textField;
  }
}

export function entityField(
  url: string,
  valueField: string,
  textField: string,
): EntityFieldBuilder {
  return new EntityFieldBuilder(url, valueField, textField);
}

/**
 * Builder for CURRENCY fields.
 *
 * Defaults applied automatically:
 *   sendAsString(true), align('right')
 *
 * Usage:
 *   new CurrencyFieldBuilder()
 *     .name('grandTotal')
 *     .label('Total')
 *     .formatter(cell => NumberUtils.formatCurrency(cell.value, cell.data?.currency))
 *     .build()
 */
export class CurrencyFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.CURRENCY);
    this._field.sendAsString = true;
    this._field.align = 'right';
  }
}

export function currencyField(): CurrencyFieldBuilder {
  return new CurrencyFieldBuilder();
}

/**
 * Factory for image upload fields (FILE type).
 *
 * Defaults: url=Constants.API_BASE_URL+'media', accept='image/*', align='center'
 * Formatter must be provided by the caller since it depends on the data shape.
 *
 * Usage:
 *   imageField(Constants.API_BASE_URL)
 *     .name('photo')
 *     .label('Imagen')
 *     .formatter(e => SharedUtils.imageFormatter(e.value?.path, e.data?.name))
 *     .build()
 */
class ImageFieldBuilder extends BaseFieldBuilder {
  constructor(apiBaseUrl: string) {
    super(FieldType.FILE);
    this._field.url = apiBaseUrl + 'media';
    this._field.accept = 'image/*';
    this._field.align = 'center';
  }
}

export function imageField(apiBaseUrl: string): ImageFieldBuilder {
  return new ImageFieldBuilder(apiBaseUrl);
}

/**
 * Builder for DATE fields.
 *
 * Defaults applied automatically:
 *   type = DATE, defaultValue = new Date()
 *
 * Usage:
 *   dateField()
 *     .name('issueDate')
 *     .label('Fecha')
 *     .formatter(cell => SharedUtils.dateFormatter(cell.value))
 *     .build()
 */
class DateFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.DATE);
    // Append T12:00:00Z so the Date sits at noon UTC — safely within any Lima calendar
    // day. A bare YYYY-MM-DD string is parsed as UTC midnight, which lima-converts back
    // to the previous day (UTC−5), causing an off-by-one on the default date.
    this._field.defaultValue = new Date(
      new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(new Date()) +
        'T12:00:00Z',
    );
  }
}

export function dateField(): DateFieldBuilder {
  return new DateFieldBuilder();
}

/**
 * Builder for DATETIME fields.
 *
 * Defaults applied automatically:
 *   type = DATETIME, defaultValue = new Date()
 *
 * Usage:
 *   datetimeField()
 *     .name('openedAt')
 *     .label('Apertura')
 *     .formatter(cell => SharedUtils.dateTimeFormatter(cell.value))
 *     .build()
 */
class DateTimeFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.DATETIME);
    this._field.defaultValue = new Date();
  }
}

export function datetimeField(): DateTimeFieldBuilder {
  return new DateTimeFieldBuilder();
}

/**
 * Builder for NUMBER fields.
 *
 * Usage:
 *   numberField()
 *     .name('quantity')
 *     .label('Cantidad')
 *     .precision(2)
 *     .align('center')
 *     .build()
 */
class NumberFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.NUMBER);
  }
}

export function numberField(): NumberFieldBuilder {
  return new NumberFieldBuilder();
}

/** A single option item for ENUM / SELECT fields. */
export type EnumOption = { value: string | number; text: string };

/**
 * Builder for ENUM (local select) fields.
 *
 * The options array is a required constructor arg — every ENUM field in the
 * codebase always provides it.
 *
 * Usage:
 *   enumField([
 *     { value: 'income', text: 'Ingreso' },
 *     { value: 'expense', text: 'Egreso' },
 *   ])
 *     .name('type')
 *     .label('Tipo')
 *     .formatter(cell => cell.value === 'income' ? 'Ingreso' : 'Egreso')
 *     .build()
 */
export class EnumFieldBuilder extends BaseFieldBuilder {
  constructor(options: EnumOption[]) {
    super(FieldType.ENUM);
    this._field.data = options;
  }
}

export function enumField(options: EnumOption[]): EnumFieldBuilder {
  return new EnumFieldBuilder(options);
}

/**
 * Builder for TEXT (plain string input) fields.
 *
 * Usage:
 *   textField()
 *     .name('businessName')
 *     .label('Razón social')
 *     .required(true)
 *     .build()
 */
export class TextFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.TEXT);
  }
}

export function textField(): TextFieldBuilder {
  return new TextFieldBuilder();
}

/**
 * Builder for TEXTAREA (multi-line text) fields.
 *
 * Usage:
 *   textareaField()
 *     .name('note')
 *     .label('Nota')
 *     .build()
 */
class TextareaFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.TEXTAREA);
  }
}

export function textareaField(): TextareaFieldBuilder {
  return new TextareaFieldBuilder();
}

/**
 * Builder for NONE (display-only, no editor) fields.
 *
 * Usage:
 *   noneField()
 *     .name('sunatStatus')
 *     .label('Envío sunat')
 *     .formatter(cell => SharedUtils.billStatusFormatter(cell.value))
 *     .build()
 */
class NoneFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.NONE);
  }
}

export function noneField(): NoneFieldBuilder {
  return new NoneFieldBuilder();
}

/**
 * Builder for SWITCH (boolean toggle) fields.
 *
 * Usage:
 *   switchField()
 *     .name('active')
 *     .label('Activo')
 *     .defaultValue(true)
 *     .formatter(SharedUtils.booleanFormatter)
 *     .build()
 */
class SwitchFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.SWITCH);
  }
}

export function switchField(): SwitchFieldBuilder {
  return new SwitchFieldBuilder();
}

/**
 * Builder for SELECT (local dropdown, value+text pairs) fields.
 *
 * Usage:
 *   selectField()
 *     .name('currency')
 *     .label('Moneda')
 *     .data([{ value: 'PEN', text: 'Soles' }, { value: 'USD', text: 'Dólares' }])
 *     .build()
 */
class SelectFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.SELECT);
  }
}

export function selectField(): SelectFieldBuilder {
  return new SelectFieldBuilder();
}

/**
 * Builder for CHECKBOX (boolean check) fields.
 *
 * Usage:
 *   checkboxField()
 *     .name('emitInvoice')
 *     .label('Emitir comprobante')
 *     .defaultValue(false)
 *     .build()
 */
class CheckboxFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.CHECKBOX);
  }
}

export function checkboxField(): CheckboxFieldBuilder {
  return new CheckboxFieldBuilder();
}

/**
 * Builder for PASSWORD (masked text input) fields.
 *
 * Usage:
 *   passwordField()
 *     .name('password')
 *     .label('Contraseña')
 *     .required(true)
 *     .build()
 */
class PasswordFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.PASSWORD);
  }
}

export function passwordField(): PasswordFieldBuilder {
  return new PasswordFieldBuilder();
}

export type { Field };
