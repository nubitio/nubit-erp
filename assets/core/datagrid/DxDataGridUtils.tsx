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
    // When valueField is "_iri", the lookup data source must inject a synthetic
    // "_iri" field (copied from "@id") on every loaded item so that DevExtreme
    // can match full IRI values (e.g. "/api/warehouses/uuid") without ever using
    // "@id" as a JS dot-notation property accessor.
    const iriMode = field.valueField === '_iri';
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
            iriMode,
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

/**
 * Builds a `calculateCellValue` function for ENTITY columns whose `valueField`
 * is `'id'` (UUID-based lookups).
 *
 * The API returns relation fields as IRI strings (e.g. `"/api/warehouses/uuid"`),
 * but the DevExtreme Lookup is configured with `valueExpr='id'` and its data store
 * is keyed by UUID.  Without this transform, the Lookup cannot match the IRI to a
 * store entry and the grid cell shows the raw IRI instead of the display name.
 *
 * This function handles three cases:
 *  - IRI string (`"/api/products/uuid"`) → extracts UUID via `split('/').pop()`
 *  - UUID string (already extracted) → returns as-is
 *  - Object with an `id` field (e.g. full entity from onChange) → returns `obj.id`
 *
 * Only applied when:
 *  - the field is an ENTITY type
 *  - `valueField === 'id'`      (UUID-based; not IRI-mode or @id-mode)
 *  - no custom formatter is set (formatters own the cell rendering entirely)
 */
export function buildEntityCellValue(field: Field): (rowData: Record<string, unknown>) => unknown {
  return (rowData: Record<string, unknown>): unknown => {
    const val = rowData[field.name];
    if (val === null || val === undefined) return val;
    if (typeof val === 'string') {
      if (val.startsWith('/')) {
        // IRI string from API — extract the last path segment (UUID)
        return val.split('/').pop() ?? val;
      }
      // Already a UUID — return as-is
      return val;
    }
    if (typeof val === 'object') {
      // Full entity object (e.g. set via onChange) — extract the id field
      const obj = val as Record<string, unknown>;
      const id = obj[field.valueField] ?? obj['id'];
      if (id !== undefined && id !== null) return String(id);
    }
    return val;
  };
}

/**
 * Builds a `cellRender` function for ENTITY columns with a primitive, non-UUID
 * `valueField` (e.g. `'code'`).
 *
 * DevExtreme ignores `calculateDisplayValue` when a `<Lookup>` child is present —
 * the Lookup's display resolution always wins.  For `code`-keyed entities the Lookup
 * resolves `"01"` → shows `"Factura"` only after `byKey` completes, which may fail or
 * be slow.  Using `cellRender` bypasses the Lookup display entirely and reads the
 * human-readable label directly from the already-embedded API response object.
 *
 * The `<Lookup>` child is still rendered for column filtering/editing purposes.
 */
export function buildEntityCellRender(
  field: Field,
): (cellData: { data: Record<string, unknown>; value?: unknown }) => React.ReactNode {
  return ({ data: rowData, value }) => {
    const val = rowData[field.name];
    // If calculateCellValue already replaced the field with a primitive, val === value === "01".
    // In that case, we can't recover the name from rowData. Check the original object shape.
    if (val !== null && val !== undefined && typeof val === 'object') {
      const obj = val as Record<string, unknown>;
      const display = obj[field.textField] ?? obj['name'];
      if (display !== undefined && display !== null) return String(display);
    }
    if (val != null) return String(val);
    // Fallback: val is already a primitive (calculateCellValue mutated rowData)
    return value != null ? String(value) : null;
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

    // For ENTITY fields with a primitive valueField (not IRI-mode), calculateCellValue
    // extracts the primitive key from any embedded object or IRI string so that
    // the DevExtreme Lookup can match it against the store for filtering/editing.
    // This covers both UUID ('id') and other primitive keys (e.g. 'code').
    //
    // Skip when:
    //  • valueField === '@id': full-IRI-mode; Lookup resolves by IRI directly.
    //  • valueField === '_iri': IRI-mode (BUG-010); Lookup resolves via iriMode dataSource.
    //  • formatter is set: formatter owns cell rendering entirely.
    const entityCellValue =
      field.type === FieldType.ENTITY &&
      !field.formatter &&
      field.valueField !== '@id' &&
      field.valueField !== '_iri'
        ? buildEntityCellValue(field)
        : undefined;

    // For non-id, non-IRI entity fields (e.g. 'code'), DevExtreme ignores
    // calculateDisplayValue when a <Lookup> child is present — the Lookup display wins.
    // Use cellRender to bypass the Lookup and read the label directly from the
    // already-embedded API response object (e.g. rowData.voucherType.name).
    //
    // id-mode and IRI-mode are excluded: id-mode relies on the Lookup byKey(uuid)
    // resolving asynchronously, and IRI-mode already works via the iriMode store.
    const entityCellRender =
      field.type === FieldType.ENTITY &&
      !field.formatter &&
      field.valueField !== '@id' &&
      field.valueField !== '_iri' &&
      field.valueField !== 'id'
        ? buildEntityCellRender(field)
        : undefined;

    // calculateDisplayValue is superseded by cellRender for code-keyed fields.
    const entityDisplayValue = undefined;

    return (
      <Column
        key={index}
        dataField={field.name}
        caption={field.label}
        alignment={field.align}
        allowSorting={field.sortable}
        allowFiltering={field.filterable}
        allowHiding={field.hideable}
        cellRender={field.formatter ?? entityCellRender}
        calculateDisplayValue={entityDisplayValue}
        calculateCellValue={entityCellValue}
        setCellValue={field.onChange}
        width={field.width}
        minWidth={field.minWidth}
        visibleIndex={field.order}
        editorOptions={editorOptions}
        editCellComponent={field.contentRender as React.ComponentType<unknown> | undefined}
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
