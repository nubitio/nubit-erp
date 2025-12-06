import { FieldType } from './FieldType';
import { Field } from './Field';
import { ValidationRule } from './ValidationRule';

export class BaseFieldBuilder {
  protected _field: Field;

  constructor(type: FieldType) {
    this._field = {
      isIdentity: false,
      type,
      col: 12,
      name: '',
      label: '',
      width: undefined,
      height: null,
      minWidth: undefined,
      minHeight: undefined,
      align: 'left',
      sortable: true,
      filterable: true,
      hideable: true,
      validators: [],
      mode: 'local',
      url: undefined,
      loadOptions: [],
      filters: [],
      byKeyUrl: null,
      textField: '',
      valueField: '',
      valueType: 'string',
      filterValue: undefined,
      selectedFilterOperation: undefined,
      data: [],
      format: '',
      formatter: null,
      itemFormatter: null,
      visible: true,
      defaultValue: undefined,
      onChange: undefined,
      onSelect: undefined,
      onClick: undefined,
      onKeyPress: undefined,
      readonly: false,
      disabled: false,
      hidden: false,
      required: false,
      precision: 0,
      accept: null,
      buttons: [],
      hidingPriority: undefined,
      searchEnabled: true,
      searchExpr: null,
      fixed: false,
      fixedPosition: undefined,
      helpText: undefined,
      contentRender: null,
      visibleOnForm: true,
      maxLength: undefined,
      multiple: false,
      editorOptions: undefined,
    };
  }

  isIdentity(isIdentity: boolean): this {
    this._field.isIdentity = isIdentity;
    return this;
  }

  col(col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12): this {
    this._field.col = col;
    return this;
  }

  name(name: string): this {
    this._field.name = name;
    return this;
  }

  label(label: string): this {
    this._field.label = label;
    return this;
  }

  width(width: number | string): this {
    this._field.width = width;
    return this;
  }

  height(height: number): this {
    this._field.height = height;
    return this;
  }

  minWidth(minWidth: number): this {
    this._field.minWidth = minWidth;
    return this;
  }

  minHeight(minHeight: number): this {
    this._field.minHeight = minHeight;
    return this;
  }

  align(align: 'left' | 'center' | 'right'): this {
    this._field.align = align;
    return this;
  }

  sortable(sortable: boolean): this {
    this._field.sortable = sortable;
    return this;
  }

  filterable(filterable: boolean): this {
    this._field.filterable = filterable;
    return this;
  }

  hideable(hideable: boolean): this {
    this._field.hideable = hideable;
    return this;
  }

  validators(validators: ValidationRule[]): this {
    this._field.validators = validators;
    return this;
  }

  mode(mode: 'remote' | 'local' | 'none'): this {
    this._field.mode = mode;
    return this;
  }

  valueType(valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime'): this {
    this._field.valueType = valueType;
    return this;
  }

  filterValue(filterValue: any): this {
    this._field.filterValue = filterValue;
    return this;
  }

  data(data: any[]): this {
    this._field.data = data;
    return this;
  }

  format(format: string): this {
    this._field.format = format;
    return this;
  }

  formatter(formatter: any): this {
    this._field.formatter = formatter;
    return this;
  }

  itemFormatter(itemFormatter: any): this {
    this._field.itemFormatter = itemFormatter;
    return this;
  }

  visible(visible: boolean): this {
    this._field.visible = visible;
    return this;
  }

  defaultValue(defaultValue: any): this {
    this._field.defaultValue = defaultValue;
    return this;
  }

  onChange(onChange: any): this {
    this._field.onChange = onChange;
    return this;
  }

  onSelect(onSelect: any): this {
    this._field.onSelect = onSelect;
    return this;
  }

  onClick(onClick: any): this {
    this._field.onClick = onClick;
    return this;
  }

  readonly(readonly: boolean): this {
    this._field.readonly = readonly;
    return this;
  }

  disabled(disabled: boolean): this {
    this._field.disabled = disabled;
    return this;
  }

  hidden(hidden: boolean): this {
    this._field.hidden = hidden;
    return this;
  }

  required(required: boolean): this {
    this._field.required = required;
    return this;
  }

  precision(value: number): this {
    this._field.precision = value;
    return this;
  }

  accept(value: any): this {
    this._field.accept = value;
    return this;
  }

  buttons(value: any[]): this {
    this._field.buttons = value;
    return this;
  }

  hidingPriority(value: number): this {
    this._field.hidingPriority = value;
    return this;
  }

  searchEnabled(value: boolean): this {
    this._field.searchEnabled = value;
    return this;
  }

  searchExpr(value: string[]): this {
    this._field.searchExpr = value;
    return this;
  }

  fixed(value: boolean): this {
    this._field.fixed = value;
    return this;
  }

  fixedPosition(value: 'left' | 'right'): this {
    this._field.fixedPosition = value;
    return this;
  }

  helpText(value: string) {
    this._field.helpText = value;
    return this;
  }

  contentRender(value: string | Function | null) {
    this._field.contentRender = value;
    return this;
  }

  visibleOnForm(value: boolean) {
    this._field.visibleOnForm = value;
    return this;
  }

  onKeyPress(param: (e) => void) {
    this._field.onKeyPress = param;
    return this;
  }

  build(): Field {
    return this._field;
  }
}
