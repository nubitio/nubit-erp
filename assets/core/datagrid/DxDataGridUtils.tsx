import { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { ToolbarButtonItem } from '../event/EventTypes';
import {
  Column,
  CustomRule,
  EmailRule,
  Item,
  ITotalItemProps,
  Lookup,
  PatternRule,
  RangeRule,
  RequiredRule,
  StringLengthRule,
  TotalItem,
} from 'devextreme-react/data-grid';
import { dxUtils } from '../../shared/data/DxUtils';
import { Button } from 'devextreme-react';
import React from 'react';

export const buildToolbar = (items: (ToolbarButtonItem | ToolbarButtonItem[])[] = []) => {
  return items?.map((item: ToolbarButtonItem | ToolbarButtonItem[], index) => {
    if (item instanceof Array) {
      return (
        <Item location="after" locateInMenu="auto" key={index}>
          {item.map((subItem: ToolbarButtonItem, subIndex: number) => {
            return (
              <Button
                key={subIndex}
                icon={subItem.icon}
                text={subItem.text}
                type={subItem.type}
                stylingMode="outlined"
                onClick={subItem.onClick}
                disabled={subItem.disabled}
              />
            );
          })}
        </Item>
      );
    }
    return (
      <Item location="after" locateInMenu="auto" key={index} visible={item.visible}>
        <Button
          icon={item.icon}
          text={item.text}
          type={item.type}
          stylingMode="outlined"
          onClick={item.onClick}
          disabled={item.disabled}
        />
      </Item>
    );
  });
};

const buildLookup = (field: Field): React.JSX.Element | null => {
  if (!field.filterable) return null;
  let lookup: React.JSX.Element | null = null;
  if (field.type === FieldType.ENTITY) {
    lookup = (
      <Lookup
        dataSource={{
          store: dxUtils.getDataStore(
            field.url ?? '',
            field.valueField,
            field.byKeyUrl,
            field.filters,
            [],
            [],
          ),
          paginate: true,
          pageSize: 10,
        }}
        valueExpr={field.valueField}
        displayExpr={field.textField}
      />
    );
  }
  if (field.type === FieldType.ENUM) {
    lookup = <Lookup dataSource={field.data} valueExpr="value" displayExpr="text" />;
  }
  if (field.type === FieldType.SWITCH) {
    const data = field.data
      ? field.data
      : [
          { value: true, text: 'Si' },
          { value: false, text: 'No' },
        ];
    lookup = <Lookup dataSource={data} valueExpr="value" displayExpr="text" />;
  }
  return lookup ?? null;
};

const buildEditorOptions = (field: Field) => {
  let editorOptions: any = {};
  if (field.type === FieldType.ENTITY) {
    editorOptions = {
      dropDownOptions: {
        minWidth: 200,
      },
      wrapItemText: true,
    };
  }
  editorOptions = {
    ...editorOptions,
    readOnly: field.readonly,
    disabled: field.disabled,
  };
  return editorOptions;
};

const buildValidationRules = (field: Field) => {
  let validationRules: any[] = [];
  if (field.required) {
    validationRules.push({
      type: 'required',
      options: {
        message: `${field.label} es requerido`,
      },
    });
  }
  validationRules = validationRules.concat(field.validators ?? []);
  return validationRules.map((rule, index) => {
    switch (rule.type) {
      case 'required':
        return <RequiredRule key={index} message={rule.options?.message} />;
      case 'email':
        return <EmailRule key={index} message={rule.options?.message} />;
      case 'pattern':
        return (
          <PatternRule
            key={index}
            pattern={rule.options?.pattern}
            message={rule.options?.message}
          />
        );
      case 'range':
        return (
          <RangeRule
            key={index}
            min={rule.options?.min}
            max={rule.options?.max}
            message={rule.options?.message}
          />
        );
      case 'stringLength':
        return (
          <StringLengthRule
            key={index}
            min={rule.options?.min}
            max={rule.options?.max}
            message={rule.options?.message}
          />
        );
      case 'custom':
        return (
          <CustomRule
            key={index}
            message={rule.options?.message}
            validationCallback={rule.options?.validationCallback}
          />
        );
      // 'async' / 'compare' not supported in DataGrid columns — use form-level validation
      default:
        return null;
    }
  });
};

/**
 * Builds a `calculateDisplayValue` function for ENTITY columns.
 *
 * When the API returns a nested object for a relation field (e.g.
 * `{ id: "uuid", name: "OTROS" }`), DevExtreme's DataGrid would normally
 * render `[object Object]`.  This function extracts the human-readable
 * display text using `field.textField` (e.g. `'name'`).
 *
 * Priority:
 *  1. `val[field.textField]`  — the configured display property
 *  2. `val['name']`           — safe fallback for Hydra entities
 *  3. `String(val)`           — last-resort coercion (never shows [object Object])
 *
 * When the cell value is already a primitive (IRI string, null, undefined),
 * it is returned as-is so existing plain-string IRIs continue to work.
 */
export function buildEntityDisplayValue(
  field: Field,
): (rowData: Record<string, unknown>) => string {
  return (rowData: Record<string, unknown>): string => {
    const val = rowData[field.name];
    if (val !== null && val !== undefined && typeof val === 'object') {
      const obj = val as Record<string, unknown>;
      const display = obj[field.textField] ?? obj['name'];
      if (display !== undefined && display !== null) {
        return String(display);
      }
      return String(val);
    }
    return val != null ? String(val) : '';
  };
}

export const buildColumns = (fields: Field[], forceLookup = false) => {
  return fields.map((field: Field, index) => {
    const clone = { ...field };
    clone.readonly = !field.filterable;
    if (!field.visible) return null;
    if (forceLookup && field.type === FieldType.NONE) return null;
    if (forceLookup) clone.filterable = true;
    const editorOptions = buildEditorOptions(clone);

    // For ENTITY fields without a custom formatter, provide a calculateDisplayValue
    // that extracts the human-readable label from nested relation objects.
    // This prevents "[object Object]" when the API returns embedded entities.
    const entityDisplayValue =
      field.type === FieldType.ENTITY && !field.formatter
        ? buildEntityDisplayValue(field)
        : undefined;

    return (
      <Column
        key={index}
        dataField={field.name}
        caption={field.label}
        alignment={field.align}
        allowSorting={field.sortable}
        allowFiltering={field.filterable}
        allowHiding={field.hideable}
        cellRender={field.formatter}
        calculateDisplayValue={entityDisplayValue}
        setCellValue={field.onChange}
        width={field.width}
        minWidth={field.minWidth}
        editorOptions={editorOptions}
        editCellComponent={field.contentRender}
        dataType={field.valueType}
        format={field.format}
        filterValue={field.filterValue}
        hidingPriority={field.hidingPriority}
        fixed={field.fixed}
        fixedPosition={field.fixedPosition}
      >
        {buildLookup(clone)}
        {buildValidationRules(clone)}
      </Column>
    );
  });
};

export const buildSummary = (fields: ITotalItemProps[] | undefined) => {
  return fields?.map((field: ITotalItemProps, index) => {
    return (
      <TotalItem
        key={index}
        column={field.column}
        summaryType={field.summaryType}
        displayFormat={field.displayFormat}
        valueFormat={field.valueFormat}
        customizeText={field.customizeText}
      />
    );
  });
};

export const getIdField = (fields: Field[]) => {
  return fields.find((field: Field) => field.isIdentity)?.name ?? '';
};
