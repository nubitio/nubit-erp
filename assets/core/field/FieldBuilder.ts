import React from 'react';
import { FieldType } from './FieldType';
import { Field, LoadOption } from './Field';
import { FilterRule } from '../common/FilterRule';
import { ValidationRule } from './ValidationRule';
import { FormatterFn, ItemFormatterFn, OnChangeFn } from './FieldCallbacks';

export class FieldBuilder {
  private readonly _field: Field;

  constructor() {
    this._field = {
      isIdentity: false,
      type: FieldType.TEXT,
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
      selectedFilterOperation: undefined,
      filterValue: undefined,
      data: [],
      format: '',
      formatter: undefined,
      itemFormatter: undefined,
      visible: true,
      defaultValue: undefined,
      autoSelectIfSingle: false,
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
      sendAsString: false,
    };
  }

  isIdentity(isIdentity: boolean): FieldBuilder {
    this._field.isIdentity = isIdentity;
    return this;
  }

  type(type: FieldType): FieldBuilder {
    this._field.type = type;
    return this;
  }

  col(col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12): FieldBuilder {
    this._field.col = col;
    return this;
  }

  name(name: string): FieldBuilder {
    this._field.name = name;
    return this;
  }

  label(label: string): FieldBuilder {
    this._field.label = label;
    return this;
  }

  width(width: number | string): FieldBuilder {
    this._field.width = width;
    return this;
  }

  height(height: number): FieldBuilder {
    this._field.height = height;
    return this;
  }

  minWidth(minWidth: number): FieldBuilder {
    this._field.minWidth = minWidth;
    return this;
  }

  minHeight(minHeight: number): FieldBuilder {
    this._field.minHeight = minHeight;
    return this;
  }

  align(align: 'left' | 'center' | 'right'): FieldBuilder {
    this._field.align = align;
    return this;
  }

  sortable(sortable: boolean): FieldBuilder {
    this._field.sortable = sortable;
    return this;
  }

  filterable(filterable: boolean): FieldBuilder {
    this._field.filterable = filterable;
    return this;
  }

  hideable(hideable: boolean): FieldBuilder {
    this._field.hideable = hideable;
    return this;
  }

  validators(validators: ValidationRule[]): FieldBuilder {
    this._field.validators = validators;
    return this;
  }

  mode(mode: 'remote' | 'local' | 'none'): FieldBuilder {
    this._field.mode = mode;
    return this;
  }

  url(url: string): FieldBuilder {
    this._field.url = url;
    return this;
  }

  loadOptions(param: LoadOption[]): FieldBuilder {
    this._field.loadOptions = param;
    return this;
  }

  filters(filters: FilterRule[]): FieldBuilder {
    this._field.filters = filters;
    return this;
  }

  byKeyUrl(byKeyUrl: string): FieldBuilder {
    this._field.byKeyUrl = byKeyUrl;
    return this;
  }

  textField(textField: string): FieldBuilder {
    this._field.textField = textField;
    return this;
  }

  valueField(valueField: string): FieldBuilder {
    this._field.valueField = valueField;
    return this;
  }

  valueType(
    valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime',
  ): FieldBuilder {
    this._field.valueType = valueType;
    return this;
  }

  selectedFilterOperation(
    operation:
      | '<'
      | '<='
      | '<>'
      | '='
      | '>'
      | '>='
      | 'between'
      | 'contains'
      | 'endswith'
      | 'notcontains'
      | 'startswith',
  ): FieldBuilder {
    this._field.selectedFilterOperation = operation;
    return this;
  }

  filterValue(filterValue: string | number | boolean | Date | null | undefined): FieldBuilder {
    this._field.filterValue = filterValue;
    return this;
  }

  data(data: Array<Record<string, unknown>>): FieldBuilder {
    this._field.data = data;
    return this;
  }

  format(format: string): FieldBuilder {
    this._field.format = format;
    return this;
  }

  formatter(formatter: FormatterFn): FieldBuilder {
    this._field.formatter = formatter;
    return this;
  }

  itemFormatter(itemFormatter: ItemFormatterFn): FieldBuilder {
    this._field.itemFormatter = itemFormatter;
    return this;
  }

  visible(visible: boolean): FieldBuilder {
    this._field.visible = visible;
    return this;
  }

  defaultValue(
    defaultValue: string | number | boolean | Date | null | undefined | (() => unknown),
  ): FieldBuilder {
    this._field.defaultValue = defaultValue;
    return this;
  }

  onChange(onChange: OnChangeFn): FieldBuilder {
    this._field.onChange = onChange;
    return this;
  }

  onSelect(onSelect: (e: unknown) => void): FieldBuilder {
    this._field.onSelect = onSelect;
    return this;
  }

  onClick(onClick: (e: unknown) => void): FieldBuilder {
    this._field.onClick = onClick;
    return this;
  }

  autoSelectIfSingle(value: boolean): FieldBuilder {
    this._field.autoSelectIfSingle = value;
    return this;
  }

  readonly(readonly: boolean): FieldBuilder {
    this._field.readonly = readonly;
    return this;
  }

  disabled(disabled: boolean): FieldBuilder {
    this._field.disabled = disabled;
    return this;
  }

  hidden(hidden: boolean): FieldBuilder {
    this._field.hidden = hidden;
    return this;
  }

  required(required: boolean): FieldBuilder {
    this._field.required = required;
    return this;
  }

  precision(value: number): FieldBuilder {
    this._field.precision = value;
    return this;
  }

  accept(value: string | null | undefined): FieldBuilder {
    this._field.accept = value;
    return this;
  }

  buttons(value: Array<Record<string, unknown>>): FieldBuilder {
    this._field.buttons = value;
    return this;
  }

  hidingPriority(value: number): FieldBuilder {
    this._field.hidingPriority = value;
    return this;
  }

  searchEnabled(value: boolean): FieldBuilder {
    this._field.searchEnabled = value;
    return this;
  }

  searchExpr(value: string[]): FieldBuilder {
    this._field.searchExpr = value;
    return this;
  }

  fixed(value: boolean): FieldBuilder {
    this._field.fixed = value;
    return this;
  }

  fixedPosition(value: 'left' | 'right'): FieldBuilder {
    this._field.fixedPosition = value;
    return this;
  }

  helpText(value: string) {
    this._field.helpText = value;
    return this;
  }

  contentRender(value: string | ((...args: unknown[]) => React.ReactNode) | null | undefined) {
    this._field.contentRender = value;
    return this;
  }

  visibleOnForm(value: boolean) {
    this._field.visibleOnForm = value;
    return this;
  }

  onKeyPress(param: (e: KeyboardEvent) => void) {
    this._field.onKeyPress = param;
    return this;
  }

  multiple(value: boolean): FieldBuilder {
    this._field.multiple = value;
    return this;
  }

  editorOptions(param: Record<string, unknown>) {
    this._field.editorOptions = param;
    return this;
  }

  sendAsString(value: boolean): FieldBuilder {
    this._field.sendAsString = value;
    return this;
  }

  maxLength(value: number): FieldBuilder {
    this._field.maxLength = value;
    return this;
  }

  build(): Field {
    return { ...this._field };
  }
}
