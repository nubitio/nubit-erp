import './NativeDataGridView.scss';
import React, {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
} from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';
import { Button, DatePicker, IconButton } from '@nubit/ui';
import { type ResourceLoadOptions, useResourceStoreFactory } from '../data/ResourceStore';
import type { DataRecord } from '@nubit/core';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { useEvents, useCoreHttpClient, getCoreLocale, getCoreTimezone, useCoreTranslation } from '@nubit/core';
import { DATA_GRID_EVENTS } from './DataGridEvents';
import type { DataGridSummaryItem, DataGridViewOptions } from './DataGridViewOptions';
import type { GridHandle } from './GridHandle';
import type { FilterRule } from '../field/FilterRule';
import type { ResourceToolbarAction, ResourceToolbarItems } from '../crud/ResourceConfig';
import { useSmartCrudRoles } from '../crud/SmartCrudRolesContext';
import { NativeEntitySelect } from '../form/NativeFormView';
import { resolveSummaryText } from '../summary';

type SortRule = { selector: string; desc: boolean };

function getPageRange(current: number, total: number): (number | null)[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i);
  const result: (number | null)[] = [0];
  if (current > 3) result.push(null);
  for (let i = Math.max(1, current - 1); i <= Math.min(total - 2, current + 1); i++) result.push(i);
  if (current < total - 4) result.push(null);
  result.push(total - 1);
  return result;
}

const PAGE_SIZE_OPTIONS = [10, 20, 50];

function getIdField(fields: Field[]): string {
  return fields.find((field) => field.isIdentity)?.name ?? 'id';
}


function getPrimitiveDisplay(value: unknown, yesLabel = 'Yes', noLabel = 'No'): string {
  if (value === null || value === undefined) return '';
  if (value instanceof Date) return value.toLocaleDateString(getCoreLocale(), { timeZone: getCoreTimezone() });
  if (typeof value === 'boolean') return value ? yesLabel : noLabel;
  if (typeof value === 'object') return '';
  return String(value);
}

function getDateDisplay(value: unknown): string {
  if (!value) return '';
  const d = value instanceof Date ? value : new Date(String(value));
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString(getCoreLocale(), { timeZone: getCoreTimezone() });
}

function getDateTimeDisplay(value: unknown): string {
  if (!value) return '';
  const d = value instanceof Date ? value : new Date(String(value));
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleString(getCoreLocale(), { timeZone: getCoreTimezone(), dateStyle: 'short', timeStyle: 'short' });
}

function getEntityDisplayValue(field: Field, value: unknown, entityOptions?: DataRecord[]): string {
  if (value && typeof value === 'object') {
    const record = value as DataRecord;
    const display = record[field.textField] ?? record['name'] ?? record['businessName'];
    return getPrimitiveDisplay(display);
  }
  // IRI string: look up display text from pre-loaded entity options
  if (value && typeof value === 'string' && entityOptions) {
    const match = entityOptions.find(
      (item) => item[field.valueField] === value || item['@id'] === value || item['_iri'] === value,
    );
    if (match) {
      const display = match[field.textField] ?? match['name'] ?? match['businessName'];
      if (display != null) return getPrimitiveDisplay(display);
    }
  }
  return getPrimitiveDisplay(value);
}

function getEnumDisplayValue(field: Field, value: unknown): string {
  const match = Array.isArray(field.data)
    ? field.data.find((item) => item['value'] === value || item[field.valueField] === value)
    : undefined;

  return getPrimitiveDisplay(match?.['text'] ?? match?.[field.textField] ?? value);
}

function getCellText(field: Field, row: DataRecord, entityOptions?: DataRecord[], yesLabel = 'Yes', noLabel = 'No'): string {
  if (field.formatter) return '';
  const value = row[field.name];
  if (field.type === FieldType.ENTITY) return getEntityDisplayValue(field, value, entityOptions);
  if (field.type === FieldType.ENUM || field.type === FieldType.SWITCH) return getEnumDisplayValue(field, value);
  if (field.type === FieldType.DATE) return getDateDisplay(value);
  if (field.type === FieldType.DATETIME) return getDateTimeDisplay(value);
  return getPrimitiveDisplay(value, yesLabel, noLabel);
}

function renderCell(field: Field, row: DataRecord, rowIndex: number, columnIndex: number, entityOptions?: DataRecord[], yesLabel = 'Yes', noLabel = 'No') {
  const value = row[field.name];
  if (field.formatter) {
    return field.formatter({ value, data: row, rowIndex, columnIndex });
  }
  if (field.type === FieldType.ENTITY) return getEntityDisplayValue(field, value, entityOptions);
  if (field.type === FieldType.ENUM || field.type === FieldType.SWITCH) {
    return getEnumDisplayValue(field, value);
  }
  if (field.type === FieldType.DATE) return getDateDisplay(value);
  if (field.type === FieldType.DATETIME) return getDateTimeDisplay(value);
  return getPrimitiveDisplay(value, yesLabel, noLabel);
}

function normalizeIcon(icon?: string): string | undefined {
  if (!icon) return undefined;
  return icon.includes('ph ') || icon.startsWith('ph-') ? icon : `ph-${icon}`;
}

function getToolbarKey(action: ResourceToolbarAction, index: number): string {
  return action.key ?? action.text ?? String(index);
}

function renderToolbarButton(action: ResourceToolbarAction, index: number, iconOnly = false) {
  if (action.visible === false) return null;
  const icon = normalizeIcon(action.icon);
  const variant = action.type === 'danger' ? 'danger' : action.type === 'primary' ? 'primary' : 'secondary';
  if (iconOnly) {
    return (
      <IconButton
        key={getToolbarKey(action, index)}
        className="native-datagrid__toolbar-icon-action"
        icon={icon ? `ph ${icon}` : 'ph ph-circle'}
        label={action.hint ?? action.text ?? ''}
        onClick={action.onClick}
        disabled={action.disabled}
      />
    );
  }

  return (
    <Button
      key={getToolbarKey(action, index)}
      variant={variant}
      size="sm"
      icon={icon ? `ph ${icon}` : undefined}
      onClick={action.onClick}
      disabled={action.disabled}
      title={action.hint}
      aria-label={action.hint ?? action.text}
    >
      {action.text && <span>{action.text}</span>}
    </Button>
  );
}

function isToolbarActionVisible(action: ResourceToolbarAction, permissions: string[]): boolean {
  if (action.visible === false) return false;
  if (!action.permission) return true;
  return permissions.includes(action.permission);
}

function renderSelectionActionItem(
  action: ResourceToolbarAction,
  index: number,
  disabled: boolean,
) {
  const icon = normalizeIcon(action.icon);
  return (
    <button
      key={getToolbarKey(action, index)}
      type="button"
      className={`native-datagrid__actions-item${action.type === 'danger' ? ' native-datagrid__actions-item--danger' : ''}`}
      onClick={action.onClick}
      disabled={disabled || action.disabled}
      role="menuitem"
    >
      {icon && <i className={`ph ${icon}`} aria-hidden="true" />}
      <span>{action.text}</span>
    </button>
  );
}

function getResolvedRowActions(
  row: DataRecord,
  rowActions: DataGridViewOptions['rowActions'],
): ResourceToolbarAction[] {
  if (!rowActions) return [];
  return typeof rowActions === 'function' ? rowActions(row) : rowActions;
}

function renderRowActionItem(
  action: ResourceToolbarAction,
  index: number,
  permissions: string[],
) {
  if (!isToolbarActionVisible(action, permissions)) return null;
  return renderSelectionActionItem(action, index, false);
}

// Shared event name with NativeFormView so lookup dropdowns and the actions
// menu close each other (single open dropdown invariant across the whole page).
const DROPDOWN_OPENED_EVENT = 'lookup:opened';

function SelectionActionsMenu({
  actionsLabel,
  selectedCount,
  actions = [],
  permissions,
}: {
  actionsLabel: string;
  selectedCount: number;
  actions?: ResourceToolbarAction[];
  permissions: string[];
}) {
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const idRef = useRef<symbol>(Symbol());

  const visibleActions = actions.filter((action) => isToolbarActionVisible(action, permissions));
  const disabled = selectedCount === 0;

  // Close on mousedown outside the container.
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (!containerRef.current?.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  // Mutual exclusion: close when any lookup/dropdown opens.
  useEffect(() => {
    const id = idRef.current;
    const handler = (e: Event) => {
      if ((e as CustomEvent<{ id: symbol }>).detail.id !== id) setOpen(false);
    };
    document.addEventListener(DROPDOWN_OPENED_EVENT, handler);
    return () => document.removeEventListener(DROPDOWN_OPENED_EVENT, handler);
  }, []);

  if (visibleActions.length === 0) return null;

  const dangerActions = visibleActions.filter((action) => action.type === 'danger');
  const regularActions = visibleActions.filter((action) => action.type !== 'danger');
  const groups = regularActions.reduce<Record<string, ResourceToolbarAction[]>>((acc, action) => {
    const group = action.group ?? 'default';
    acc[group] = acc[group] ?? [];
    acc[group].push(action);
    return acc;
  }, {});

  const label = selectedCount > 0 ? `${actionsLabel} (${selectedCount})` : actionsLabel;

  const handleToggle = () => {
    if (disabled) return;
    const next = !open;
    if (next) {
      document.dispatchEvent(new CustomEvent<{ id: symbol }>(DROPDOWN_OPENED_EVENT, { detail: { id: idRef.current } }));
    }
    setOpen(next);
  };

  return (
    <div ref={containerRef} className="native-datagrid__actions-menu">
      <Button
        variant="secondary"
        aria-disabled={disabled}
        aria-expanded={open}
        onClick={handleToggle}
      >
        <span>{label}</span>
        <i className="ph ph-caret-down" aria-hidden="true" />
      </Button>
      {open && !disabled && (
        <div className="native-datagrid__actions-popover" role="menu">
          {Object.entries(groups).map(([group, items]) => (
            <div key={group} className="native-datagrid__actions-group">
              {items.length > 1 && items[0]?.groupLabel && (
                <div className="native-datagrid__actions-group-label">
                  {items[0].groupLabel}
                </div>
              )}
              {items.map((action, index) => renderSelectionActionItem(action, index, disabled))}
            </div>
          ))}
          {dangerActions.length > 0 && (
            <div className="native-datagrid__actions-danger-group">
              {dangerActions.map((action, index) => renderSelectionActionItem(action, index, disabled))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function buildToolbar(
  options: DataGridViewOptions,
  t: ReturnType<typeof useCoreTranslation>['t'],
  onAddClick: () => void,
): ResourceToolbarItems {
  return {
    primary: options.toolbar?.primary ?? (options.allowAdd
      ? [{
          text: t('grid.buttonNew'),
          icon: 'ph-plus',
          type: 'primary',
          onClick: onAddClick,
          disabled: options.addDisabled,
        }]
      : undefined),
    selection: options.toolbar?.selection,
    utility: options.toolbar?.utility,
    showRefresh: options.toolbar?.showRefresh ?? true,
  };
}

type FilterOperator = { value: string; label: string };
const BETWEEN_VALUE_SEPARATOR = '|';

function isDateLikeField(field: Field): boolean {
  return field.type === FieldType.DATE || field.type === FieldType.DATETIME;
}

function getDateStartOfDay(value: string): string {
  return `${value} 00:00:00`;
}

function getDateEndOfDay(value: string): string {
  return `${value} 23:59:59`;
}

function splitBetweenValue(value: string): [string, string] {
  const [start = '', end = ''] = value.split(BETWEEN_VALUE_SEPARATOR);
  return [start, end];
}

function joinBetweenValue(start: string, end: string): string {
  if (!start && !end) return '';
  return `${start}${BETWEEN_VALUE_SEPARATOR}${end}`;
}

function getDefaultFilterOperator(field: Field): string {
  if (field.selectedFilterOperation) return field.selectedFilterOperation;
  switch (field.type) {
    case FieldType.NUMBER:
    case FieldType.CURRENCY:
    case FieldType.DATE:
    case FieldType.DATETIME:
    case FieldType.ENUM:
    case FieldType.SELECT:
    case FieldType.SWITCH:
    case FieldType.ENTITY:
      return '=';
    default:
      return 'contains';
  }
}

function getFilterOperators(field: Field): FilterOperator[] {
  switch (field.type) {
    case FieldType.NUMBER:
    case FieldType.CURRENCY:
      return [
        { value: '=', label: '=' },
        { value: '<>', label: '≠' },
        { value: '>', label: '>' },
        { value: '>=', label: '≥' },
        { value: '<', label: '<' },
        { value: '<=', label: '≤' },
      ];
    case FieldType.DATE:
    case FieldType.DATETIME:
      return [
        { value: '=', label: '=' },
        { value: '<>', label: '≠' },
        { value: '>', label: '>' },
        { value: '>=', label: '≥' },
        { value: '<', label: '<' },
        { value: '<=', label: '≤' },
        { value: 'between', label: '↔' },
      ];
    case FieldType.ENUM:
    case FieldType.SELECT:
    case FieldType.SWITCH:
    case FieldType.ENTITY:
      return [{ value: '=', label: '=' }];
    default:
      return [
        { value: 'contains', label: '~' },
        { value: 'notcontains', label: '!~' },
        { value: 'startswith', label: '^' },
        { value: '=', label: '=' },
        { value: '<>', label: '≠' },
      ];
  }
}

function computeDefaultOperators(fields: Field[]): Record<string, string> {
  const defaults: Record<string, string> = {};
  fields.forEach((field) => {
    if (field.filterable) defaults[field.name] = getDefaultFilterOperator(field);
  });
  return defaults;
}

function buildFilterExpression(
  filters: Record<string, string>,
  operators: Record<string, string>,
  fields: Field[],
): unknown[] {
  return Object.entries(filters)
    .filter(([, value]) => value.trim() !== '')
    .flatMap(([fieldName, value]) => {
      const field = fields.find((candidate) => candidate.name === fieldName);
      const operator = operators[fieldName] ?? 'contains';

      if (field && isDateLikeField(field)) {
        if (operator === 'between') {
          const [start, end] = splitBetweenValue(value);
          return [
            ...(start ? [[fieldName, '>=', field.type === FieldType.DATETIME ? getDateStartOfDay(start) : start]] : []),
            ...(end ? [[fieldName, '<=', field.type === FieldType.DATETIME ? getDateEndOfDay(end) : end]] : []),
          ];
        }

        if (field.type === FieldType.DATETIME && operator === '=') {
          return [
            [fieldName, '>=', getDateStartOfDay(value)],
            [fieldName, '<=', getDateEndOfDay(value)],
          ];
        }
      }

      return [[fieldName, operator, value]];
    });
}

function FilterCell({
  field,
  operator,
  remoteOptions,
  value,
  onClear,
  onInputChange,
  onBetweenInputChange,
  onSelectChange,
  onOperatorChange,
}: {
  field: Field;
  operator: string;
  remoteOptions: DataRecord[];
  value: string;
  onClear: () => void;
  onInputChange: (value: string) => void;
  onBetweenInputChange: (start: string, end: string) => void;
  onSelectChange: (value: string) => void;
  onOperatorChange: (operator: string) => void;
}) {
  const httpClient = useCoreHttpClient();

  if (field.type === FieldType.ENTITY) {
    return (
      <NativeEntitySelect
        className="native-datagrid__filter-select"
        field={field}
        httpClient={httpClient}
        options={remoteOptions}
        value={value}
        onChange={(nextVal) => onSelectChange(nextVal ? String(nextVal) : '')}
      />
    );
  }

  const isDropdown =
    field.type === FieldType.ENUM ||
    field.type === FieldType.SELECT ||
    field.type === FieldType.SWITCH;

  if (isDropdown) {
    const items = field.data ?? [];
    return (
      <select
        className="native-datagrid__filter-select"
        value={value}
        aria-label={`Filtrar ${field.label}`}
        onChange={(e) => onSelectChange(e.target.value)}
      >
        <option value="">Todos</option>
        {items.map((item) => (
          <option key={String(item['value'] ?? '')} value={String(item['value'] ?? '')}>
            {String(item['text'] ?? item['value'] ?? '')}
          </option>
        ))}
      </select>
    );
  }

  const operators = getFilterOperators(field);
  const inputType = field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY ? 'number' : 'text';
  const [betweenStart, betweenEnd] = splitBetweenValue(value);
  const isBetween = isDateLikeField(field) && operator === 'between';

  return (
    <div className={`native-datagrid__filter-wrap${isBetween ? ' native-datagrid__filter-wrap--between' : ''}`}>
      {operators.length > 1 ? (
        <select
          className="native-datagrid__filter-operator"
          value={operator}
          aria-label="Operador de filtro"
          onChange={(e) => onOperatorChange(e.target.value)}
        >
          {operators.map((op) => (
            <option key={op.value} value={op.value}>{op.label}</option>
          ))}
        </select>
      ) : (
        <i className="ph ph-magnifying-glass native-datagrid__filter-icon" aria-hidden="true" />
      )}
      {isBetween ? (
        <div className="native-datagrid__filter-between-inputs">
          <DatePicker
            className="native-datagrid__filter-date"
            clearable={false}
            locale={getCoreLocale()}
            value={betweenStart}
            ariaLabel={`Filtrar ${field.label} desde`}
            onChange={(nextValue) => onBetweenInputChange(nextValue, betweenEnd)}
          />
          <DatePicker
            className="native-datagrid__filter-date"
            clearable={false}
            locale={getCoreLocale()}
            value={betweenEnd}
            ariaLabel={`Filtrar ${field.label} hasta`}
            onChange={(nextValue) => onBetweenInputChange(betweenStart, nextValue)}
          />
        </div>
      ) : isDateLikeField(field) ? (
        <DatePicker
          className="native-datagrid__filter-date"
          clearable={false}
          locale={getCoreLocale()}
          value={value}
          ariaLabel={`Filtrar ${field.label}`}
          onChange={onInputChange}
        />
      ) : (
        <input
          type={inputType}
          className="native-datagrid__filter-input"
          value={value}
          aria-label={`Filtrar ${field.label}`}
          onChange={(e) => onInputChange(e.target.value)}
        />
      )}
      <button
        type="button"
        className={`native-datagrid__filter-clear${value ? ' is-active' : ''}`}
        aria-label="Limpiar filtro"
        tabIndex={value ? 0 : -1}
        onClick={onClear}
      >
        <i className="ph ph-x" aria-hidden="true" />
      </button>
    </div>
  );
}

function SummaryFooter({
  fields,
  hasCheckbox,
  hasDetail,
  hasRowActions,
  rows,
  summaryFields,
}: {
  fields: Field[];
  hasCheckbox: boolean;
  hasDetail: boolean;
  hasRowActions: boolean;
  rows: DataRecord[];
  summaryFields?: DataGridSummaryItem[];
}) {
  if (!summaryFields?.length) return null;

  const itemsByColumn = new Map(summaryFields.filter((item) => item.column).map((item) => [item.column, item]));
  const unboundItems = summaryFields.filter((item) => !item.column);
  const fallbackFieldName = fields[fields.length - 1]?.name;

  return (
    <tfoot className="native-datagrid__summary-footer">
      <tr>
        {hasDetail && <td className="native-datagrid__detail-cell" />}
        {hasCheckbox && <td className="native-datagrid__select-cell" />}
        {fields.map((field) => {
          const item = itemsByColumn.get(field.name) ?? (field.name === fallbackFieldName ? unboundItems[0] : undefined);
          const align = item?.align ?? field.align;
          const justifyContent = align === 'center' ? 'center' : align === 'right' ? 'flex-end' : 'flex-start';
          return (
            <td key={field.name} style={align ? { textAlign: align } : undefined}>
              {item && (
                <div className="native-datagrid__summary-cell" style={{ justifyContent }}>
                  {item.label && <span className="native-datagrid__summary-label">{item.label}</span>}
                  <span className="native-datagrid__summary-value">{resolveSummaryText(rows, item)}</span>
                </div>
              )}
            </td>
          );
        })}
        {hasRowActions && <td className="native-datagrid__actions-cell" />}
      </tr>
    </tfoot>
  );
}

function DetailRows({ fields, url }: { fields: Field[]; url: string }) {
  const httpClient = useCoreHttpClient();
  const resourceStoreFactory = useResourceStoreFactory();
  const { t } = useCoreTranslation();
  const [rows, setRows] = useState<DataRecord[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const source = resourceStoreFactory({ url, idField: getIdField(fields), httpClient });
    setLoading(true);
    source
      .load({})
      .then((result) => setRows(result.data))
      .finally(() => setLoading(false));
  }, [fields, httpClient, resourceStoreFactory, url]);

  if (loading) return <div className="native-datagrid__loading">{t('grid.loading')}</div>;
  if (rows.length === 0) return <div className="native-datagrid__empty">{t('grid.noRecords')}</div>;

  return (
    <div className="native-datagrid__detail-content">
      <table className="native-datagrid__detail-table">
        <thead>
          <tr>
            {fields.filter((field) => field.visible && !field.hidden).map((field) => (
              <th key={field.name}>{field.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIndex) => (
            <tr key={String(row[getIdField(fields)] ?? rowIndex)}>
              {fields.filter((field) => field.visible && !field.hidden).map((field, columnIndex) => (
                <td key={field.name}>{renderCell(field, row, rowIndex, columnIndex, undefined, t('common.yes'), t('common.no'))}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export const NativeDataGridView = forwardRef<GridHandle, DataGridViewOptions>((options, ref) => {
  const { t } = useCoreTranslation();
  const httpClient = useCoreHttpClient();
  const resourceStoreFactory = useResourceStoreFactory();
  const toolbarPermissions = useSmartCrudRoles();
  const navigate = useNavigate();
  const [on, emit] = useEvents();
  const idField = useMemo(() => getIdField(options.fields), [options.fields]);
  const visibleFields = useMemo(
    () => options.fields
      .filter((field) => field.visible && !field.hidden)
      .filter((field) => options.visibleColumns == null || options.visibleColumns.includes(field.name))
      .sort((a, b) => (a.order ?? Number.MAX_SAFE_INTEGER) - (b.order ?? Number.MAX_SAFE_INTEGER)),
    [options.fields, options.visibleColumns],
  );
  const [rows, setRows] = useState<DataRecord[]>([]);
  const rowsRef = useRef<DataRecord[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [selectedKeys, setSelectedKeys] = useState<unknown[]>([]);
  const [filters, setFilters] = useState<Record<string, string>>({});
  const [filterInputs, setFilterInputs] = useState<Record<string, string>>({});
  const filterDebounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const [filterOperators, setFilterOperators] = useState<Record<string, string>>(() => computeDefaultOperators(options.fields));
  const [filterRemoteOptions, setFilterRemoteOptions] = useState<Record<string, DataRecord[]>>({});
  const loadedFilterOptionsRef = useRef<Set<string>>(new Set());
  const [sort, setSort] = useState<SortRule[]>(options.sort ?? []);
  const [pageSize, setPageSize] = useState(options.pageSize ?? 20);
  const [confirmRow, setConfirmRow] = useState<DataRecord | null>(null);
  const [page, setPage] = useState(0);
  const [expandedKeys, setExpandedKeys] = useState<Set<unknown>>(() => new Set());
  const [isGridLoading, setIsGridLoading] = useState(!options.manualLoad);
  const [loadingMessage, setLoadingMessage] = useState('');
  const [openRowMenu, setOpenRowMenu] = useState<{
    key: unknown;
    rect: DOMRect;
    actions: ResourceToolbarAction[];
  } | null>(null);
  const onContentReadyRef = useRef(options.onContentReady);
  const onRowPreparedRef = useRef(options.onRowPrepared);
  const tableWrapRef = useRef<HTMLDivElement>(null);
  const [colWidths, setColWidths] = useState<Record<string, number>>({});
  const [tableWidth, setTableWidth] = useState<number | undefined>();
  const resizingRef = useRef<{ name: string; startX: number; startWidth: number; startTableWidth: number } | null>(null);

  useEffect(() => {
    onContentReadyRef.current = options.onContentReady;
  }, [options.onContentReady]);

  useEffect(() => {
    onRowPreparedRef.current = options.onRowPrepared;
  }, [options.onRowPrepared]);

  // Row actions menu: close on outside click, Escape, or when rows change
  const closeRowMenu = useCallback(() => {
    setOpenRowMenu(null);
  }, []);

  useEffect(() => {
    if (!openRowMenu) return;

    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeRowMenu();
    };
    const onPointerDown = (e: PointerEvent) => {
      const target = e.target as HTMLElement;
      if (!target.closest('.native-datagrid__row-actions-popover')) {
        closeRowMenu();
      }
    };
    const onScroll = () => {
      closeRowMenu();
    };

    document.addEventListener('keydown', onKeyDown);
    document.addEventListener('pointerdown', onPointerDown);

    // Close on scroll of the table (very common when user scrolls while menu is open)
    const wrap = tableWrapRef.current;
    wrap?.addEventListener('scroll', onScroll, { passive: true });

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.removeEventListener('pointerdown', onPointerDown);
      wrap?.removeEventListener('scroll', onScroll);
    };
  }, [openRowMenu, closeRowMenu]);

  // Close row menu if the underlying rows change (defensive)
  useEffect(() => {
    if (openRowMenu) closeRowMenu();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows.length]);

  // Close row menu when delete confirm dialog opens
  useEffect(() => {
    if (confirmRow && openRowMenu) {
      closeRowMenu();
    }
  }, [confirmRow, openRowMenu, closeRowMenu]);

  const source = useMemo(
    () =>
      resourceStoreFactory({
        url: options.url,
        idField,
        defaultFilterRules: options.filter as unknown[][] | undefined,
        defaultSortRules: sort,
        httpClient,
      }),
    [httpClient, idField, options.filter, options.url, resourceStoreFactory, sort],
  );

  const loadRows = useCallback(async () => {
    if (options.manualLoad) return rowsRef.current;
    setIsGridLoading(true);
    const loadOptions: ResourceLoadOptions = {
      filter: buildFilterExpression(filters, filterOperators, options.fields),
      sort,
    };
    if (options.paging ?? true) {
      loadOptions.skip = page * pageSize;
      loadOptions.take = pageSize;
    }

    const result = await source.load(loadOptions);
    rowsRef.current = result.data;
    setRows(result.data);
    setTotalCount(result.totalCount);
    setIsGridLoading(false);
    onContentReadyRef.current?.();
    return result.data;
  }, [filterOperators, filters, options.fields, options.manualLoad, options.paging, page, pageSize, sort, source]);

  useEffect(() => {
    void loadRows();
  }, [loadRows]);

  useEffect(() => {
    rows.forEach((row, rowIndex) => onRowPreparedRef.current?.({ data: row, rowIndex }));
  }, [rows]);

  useEffect(() => {
    if (!options.events?.SUCCESS) return;
    const sub = on(options.events.SUCCESS, () => void loadRows());
    return () => sub.unsubscribe();
  }, [loadRows, on, options.events]);

  useEffect(() => {
    visibleFields
      .filter((f) => f.type === FieldType.ENTITY && f.url && !loadedFilterOptionsRef.current.has(f.name))
      .forEach((f) => {
        loadedFilterOptionsRef.current.add(f.name);
        resourceStoreFactory({ url: f.url!, idField: f.valueField, httpClient, iriMode: true })
          .load({ take: 500 })
          .then((result) => setFilterRemoteOptions((prev) => ({ ...prev, [f.name]: result.data })))
          .catch(() => {});
      });
  }, [httpClient, resourceStoreFactory, visibleFields]);

  // Snapshot of mutable state read by the imperative handle. Updated after every render
  // so handle methods always see current values without the handle itself being rebuilt.
  const handleStateRef = useRef({ selectedKeys, filters, filterOperators, loadRows, idField });
  handleStateRef.current = { selectedKeys, filters, filterOperators, loadRows, idField }; // eslint-disable-line react-hooks/refs

  useImperativeHandle(ref, () => ({
    showLoading: (message) => {
      setLoadingMessage(message ?? t('grid.loading'));
      setIsGridLoading(true);
    },
    hideLoading: () => setIsGridLoading(false),
    refresh: () => void handleStateRef.current.loadRows(),
    reset: () => {
      clearTimeout(filterDebounceRef.current);
      setSelectedKeys([]);
      setFilters({});
      setFilterInputs({});
      setFilterOperators(computeDefaultOperators(options.fields));
      setSort([]);
      setPage(0);
      setExpandedKeys(new Set());
    },
    loadData: () => Promise.resolve(rowsRef.current),
    getSelectedRowKey: () => {
      const key = handleStateRef.current.selectedKeys[0];
      return typeof key === 'string' || typeof key === 'number' ? key : undefined;
    },
    getSelectedRow: () => rowsRef.current.find((row) => row[handleStateRef.current.idField] === handleStateRef.current.selectedKeys[0]),
    getSelectedRowKeys: () => handleStateRef.current.selectedKeys,
    getSelectedRows: () => rowsRef.current.filter((row) => handleStateRef.current.selectedKeys.includes(row[handleStateRef.current.idField])),
    getFilter: () => buildFilterExpression(handleStateRef.current.filters, handleStateRef.current.filterOperators, options.fields),
    filter: (filterRule: FilterRule | null) => {
      const { filterOperators: currentOps } = handleStateRef.current;
      const nextFilters = filterRule ? { [filterRule.field]: String(filterRule.value ?? '') } : {};
      const nextOperators = filterRule
        ? { ...currentOps, [filterRule.field]: filterRule.operator || getDefaultFilterOperator(options.fields.find((f) => f.name === filterRule.field) ?? options.fields[0]) }
        : computeDefaultOperators(options.fields);
      clearTimeout(filterDebounceRef.current);
      setFilters(nextFilters);
      setFilterInputs(nextFilters);
      setFilterOperators(nextOperators);
      setPage(0);
    },
  }), [options.fields, t]);

  const selectedRows = rows.filter((row) => selectedKeys.includes(row[idField]));

  const selectRow = (row: DataRecord) => {
    const key = row[idField];
    const nextKeys =
      options.selectionMode === 'multiple'
        ? selectedKeys.includes(key)
          ? selectedKeys.filter((selectedKey) => selectedKey !== key)
          : [...selectedKeys, key]
        : [key];

    setSelectedKeys(nextKeys);
    const nextRows = rows.filter((item) => nextKeys.includes(item[idField]));
    options.onSelectionChanged?.({ selectedRowsData: nextRows });
    emit(DATA_GRID_EVENTS.SELECTION_CHANGED, nextRows);
  };

  const onAddClick = () => {
    if (options.onAdd) {
      options.onAdd();
      return;
    }
    if (options.events?.ADD) emit(options.events.ADD);
  };
  const confirmDelete = () => {
    if (confirmRow) {
      if (options.onDelete) {
        options.onDelete(confirmRow);
      } else if (options.events?.DELETE) {
        emit(options.events.DELETE, { row: confirmRow });
      }
    }
    setConfirmRow(null);
  };

  const toggleSort = (field: Field) => {
    if (!field.sortable) return;
    setPage(0);
    setSort((current) => {
      const existing = current.find((rule) => rule.selector === field.name);
      if (!existing) return [{ selector: field.name, desc: false }];
      if (!existing.desc) return [{ selector: field.name, desc: true }];
      return [];
    });
  };

  const handleResizeMouseDown = (fieldName: string) => (event: React.MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    const th = (event.currentTarget as HTMLElement).closest('th') as HTMLTableCellElement | null;
    if (!th) return;
    const table = th.closest('table') as HTMLTableElement | null;

    resizingRef.current = {
      name: fieldName,
      startX: event.clientX,
      startWidth: th.offsetWidth,
      startTableWidth: table?.offsetWidth ?? tableWrapRef.current?.clientWidth ?? th.offsetWidth,
    };

    const onMouseMove = (moveEvent: MouseEvent) => {
      if (!resizingRef.current) return;
      const resize = resizingRef.current;
      const next = Math.max(48, resize.startWidth + (moveEvent.clientX - resize.startX));
      const actualDelta = next - resize.startWidth;
      setColWidths((current) => ({ ...current, [resize.name]: next }));
      setTableWidth(Math.max(tableWrapRef.current?.clientWidth ?? 0, resize.startTableWidth + actualDelta));
    };
    const onMouseUp = () => {
      resizingRef.current = null;
      window.removeEventListener('mousemove', onMouseMove);
      window.removeEventListener('mouseup', onMouseUp);
    };

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
  };

  const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
  const hasCheckbox = options.selectionMode === 'multiple';
  const hasBuiltInRowActions = Boolean(
    (options.allowEdit && (options.onEdit || options.events?.EDIT)) ||
    (options.allowDelete && (options.onDelete || options.events?.DELETE)),
  );
  const hasRowActions = Boolean(
    options.mode !== 'minimal' && (
      hasBuiltInRowActions ||
      options.rowActions
    ),
  );
  const colSpan = visibleFields.length + (hasCheckbox ? 1 : 0) + (options.detailFields ? 1 : 0) + (hasRowActions ? 1 : 0);
  const toolbar = buildToolbar(options, t, onAddClick);

  return (
    <div className="view datagrid-list">
      <div className={`view-wrapper ${options.mode === 'full' || !options.mode ? 'view-wrapper-datagrid-list list-page' : ''}`}>
        <div className="grid theme-dependent native-datagrid">
          {(options.toolbarVisible ?? true) && options.mode !== 'minimal' && (
            <div className="native-datagrid__toolbar">
              {options.hasBackButton && (
                <IconButton
                  icon="ph ph-arrow-left"
                  label="Volver"
                  onClick={() => (options.onBack ? options.onBack() : navigate(-1))}
                />
              )}
              {options.mode === 'full' || !options.mode ? <div className="grid-header">{options.title}</div> : null}
              {options.beforeToolbar?.()}
              <div className="native-datagrid__toolbar-spacer" />
              {toolbar.primary?.filter((action) => isToolbarActionVisible(action, toolbarPermissions)).map((action, index) => renderToolbarButton(action, index))}
              <SelectionActionsMenu actionsLabel={t('grid.buttonActions')} selectedCount={selectedRows.length} actions={toolbar.selection} permissions={toolbarPermissions} />
              {toolbar.utility?.filter((action) => isToolbarActionVisible(action, toolbarPermissions)).map((action, index) => renderToolbarButton(action, index, true))}
              {toolbar.showRefresh !== false && (
                <IconButton icon="ph ph-arrow-clockwise" label={t('grid.buttonRefresh')} onClick={() => void loadRows()} />
              )}
            </div>
          )}
           <div className="native-datagrid__table-wrap" ref={tableWrapRef}>
            <table className="native-datagrid__table" style={tableWidth ? { width: tableWidth } : undefined}>
              <thead>
                <tr>
                  {options.detailFields && <th className="native-datagrid__detail-cell" style={{ width: 36 }} />}
                  {hasCheckbox && <th className="native-datagrid__select-cell" />}
                  {visibleFields.map((field) => {
                    const sortRule = sort.find((rule) => rule.selector === field.name);
                    const width = colWidths[field.name] ?? field.width;
                    return (
                      <th key={field.name} title={field.label} style={{ width, minWidth: field.minWidth, textAlign: field.align }}>
                        {field.sortable ? (
                          <button type="button" className="native-datagrid__sort-button" onClick={() => toggleSort(field)}>
                            <span>{field.label}</span>
                            {sortRule ? (
                              <i className={`ph ${sortRule.desc ? 'ph-caret-down' : 'ph-caret-up'} native-datagrid__sort-icon`} aria-hidden="true" />
                            ) : (
                              <i className="ph ph-caret-up-down native-datagrid__sort-hint" aria-hidden="true" />
                            )}
                          </button>
                        ) : (
                          <span className="native-datagrid__column-label">{field.label}</span>
                        )}
                        <span
                          className="native-datagrid__col-resize"
                          onMouseDown={handleResizeMouseDown(field.name)}
                          onClick={(event) => event.stopPropagation()}
                          title="Arrastrar para redimensionar"
                        />
                      </th>
                    );
                  })}
                  {hasRowActions && <th className="native-datagrid__actions-cell" />}
                </tr>
                {(options.filterRow ?? true) && (
                  <tr className="native-datagrid__filter-row">
                    {options.detailFields && <td className="native-datagrid__detail-cell" />}
                    {hasCheckbox && <td className="native-datagrid__select-cell" />}
                    {visibleFields.map((field) => {
                      const applyImmediate = (nextInputs: Record<string, string>) => {
                        clearTimeout(filterDebounceRef.current);
                        setFilterInputs(nextInputs);
                        setFilters(nextInputs);
                        setPage(0);
                        options.onFilterChange?.(nextInputs);
                      };
                      return (
                      <td key={field.name} style={{ minWidth: field.minWidth }} className={field.filterable === false ? 'native-datagrid__filter-cell--noop' : undefined}>
                        {field.filterable ? (
                          <FilterCell
                            field={field}
                            operator={filterOperators[field.name] ?? getDefaultFilterOperator(field)}
                            remoteOptions={filterRemoteOptions[field.name] ?? []}
                            value={filterInputs[field.name] ?? ''}
                            onInputChange={(nextValue) => {
                              const nextInputs = { ...filterInputs, [field.name]: nextValue };
                              if (nextValue === '') delete nextInputs[field.name];
                              setFilterInputs(nextInputs);
                              clearTimeout(filterDebounceRef.current);
                              filterDebounceRef.current = setTimeout(() => {
                                setFilters(nextInputs);
                                setPage(0);
                                options.onFilterChange?.(nextInputs);
                              }, 300);
                            }}
                            onBetweenInputChange={(start, end) => {
                              const nextValue = joinBetweenValue(start, end);
                              const nextInputs = { ...filterInputs, [field.name]: nextValue };
                              if (nextValue === '') delete nextInputs[field.name];
                              applyImmediate(nextInputs);
                            }}
                            onSelectChange={(nextValue) => {
                              const nextInputs = { ...filterInputs, [field.name]: nextValue };
                              if (nextValue === '') delete nextInputs[field.name];
                              applyImmediate(nextInputs);
                            }}
                            onOperatorChange={(nextOperator) => {
                              setFilterOperators((prev) => ({ ...prev, [field.name]: nextOperator }));
                              const currentValue = filterInputs[field.name];
                              if (currentValue) {
                                const nextInputs = { ...filterInputs };
                                if (nextOperator === 'between' && !currentValue.includes(BETWEEN_VALUE_SEPARATOR)) {
                                  nextInputs[field.name] = joinBetweenValue(currentValue, '');
                                }
                                if (nextOperator !== 'between' && currentValue.includes(BETWEEN_VALUE_SEPARATOR)) {
                                  nextInputs[field.name] = splitBetweenValue(currentValue).find(Boolean) ?? '';
                                }
                                applyImmediate(nextInputs);
                              }
                            }}
                            onClear={() => {
                              const nextInputs = { ...filterInputs };
                              delete nextInputs[field.name];
                              applyImmediate(nextInputs);
                            }}
                          />
                        ) : null}
                      </td>
                    );
                    })}
                    {hasRowActions && <td className="native-datagrid__actions-cell" />}
                  </tr>
                )}
                {options.errorMessage && (
                  <tr className="native-datagrid__error-row">
                    <td colSpan={colSpan}>
                      <div className="native-datagrid__error-message" role="alert">
                        <i className="ph ph-warning-circle" aria-hidden="true" />
                        <span>{options.errorMessage}</span>
                        {options.onDismissError && (
                          <button
                            type="button"
                            className="native-datagrid__error-close"
                            aria-label="Cerrar error"
                            onClick={options.onDismissError}
                          >
                            <i className="ph ph-x" aria-hidden="true" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                )}
              </thead>
              <tbody>
                {rows.length === 0 && isGridLoading ? (
                  Array.from({ length: 7 }, (_, i) => (
                    <tr key={`skeleton-${i}`} className="native-datagrid__skeleton-row">
                      {options.detailFields && <td className="native-datagrid__detail-cell" />}
                      {hasCheckbox && <td className="native-datagrid__select-cell"><div className="native-datagrid__skeleton-cell" style={{ width: 16, height: 16 }} /></td>}
                      {visibleFields.map((field) => (
                        <td key={field.name}><div className="native-datagrid__skeleton-cell" /></td>
                      ))}
                      {hasRowActions && <td className="native-datagrid__actions-cell" />}
                    </tr>
                  ))
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={colSpan} className="native-datagrid__empty-td" />
                  </tr>
                ) : (
                  rows.map((row, rowIndex) => {
                    const key = row[idField] ?? rowIndex;
                    const detailFields =
                      typeof options.detailFields === 'function'
                        ? options.detailFields(row)
                        : options.detailFields;
                    const detailUrl = options.detailUrl?.replace('{id}', String(key));
                    const expanded = expandedKeys.has(key);
                    const rowActions: ResourceToolbarAction[] = [
                      ...(options.allowEdit && (options.onEdit || options.events?.EDIT)
                        ? [{
                            text: t('grid.buttonEdit'),
                            icon: 'ph-pencil-simple',
                            disabled: options.editDisabled,
                            onClick: () => {
                              if (options.onEdit) options.onEdit(row);
                              else emit(options.events!.EDIT!, { row });
                            },
                          }]
                        : []),
                      ...getResolvedRowActions(row, options.rowActions),
                      ...(options.allowDelete && (options.onDelete || options.events?.DELETE)
                        ? [{
                            text: t('grid.buttonDelete'),
                            icon: 'ph-trash',
                            type: 'danger',
                            disabled: options.deleteDisabled,
                            onClick: () => setConfirmRow(row),
                          }]
                        : []),
                    ];

                    return (
                      <React.Fragment key={String(key)}>
                        <tr
                          className={`native-datagrid__row ${expanded ? 'native-datagrid__row--expanded' : ''} ${selectedKeys.includes(key) ? 'native-datagrid__row--selected' : ''}`}
                          tabIndex={0}
                          onClick={() => selectRow(row)}
                          onKeyDown={(event) => {
                            if (event.key === 'Enter' && options.allowEdit && (options.onEdit || options.events?.EDIT)) {
                              if (options.onEdit) options.onEdit(row);
                              else emit(options.events!.EDIT!, { row });
                            } else if (event.key === 'Delete' && options.allowDelete && (options.onDelete || options.events?.DELETE)) {
                              setConfirmRow(row);
                            } else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                              event.preventDefault();
                              const allRows = event.currentTarget.closest('tbody')?.querySelectorAll<HTMLTableRowElement>('tr.native-datagrid__row');
                              if (!allRows) return;
                              const idx = Array.from(allRows).indexOf(event.currentTarget);
                              const next = allRows[event.key === 'ArrowUp' ? idx - 1 : idx + 1];
                              if (next) { next.focus(); selectRow(rows[event.key === 'ArrowUp' ? idx - 1 : idx + 1]); }
                            }
                          }}
                        >
                          {options.detailFields && (
                            <td className="native-datagrid__detail-cell">
                              {detailUrl && detailFields && (
                                <button
                                  type="button"
                                  className={`native-datagrid__expand-button${expanded ? ' is-expanded' : ''}`}
                                  aria-label={expanded ? 'Contraer detalle' : 'Expandir detalle'}
                                  onClick={(event) => {
                                    event.stopPropagation();
                                    setExpandedKeys((current) => {
                                      const next = new Set(current);
                                      if (next.has(key)) next.delete(key);
                                      else next.add(key);
                                      return next;
                                    });
                                  }}
                                >
                                  <i className={`ph ${expanded ? 'ph-caret-down' : 'ph-caret-right'}`} aria-hidden="true" />
                                </button>
                              )}
                            </td>
                          )}
                          {hasCheckbox && (
                            <td className="native-datagrid__select-cell">
                              <input
                                type="checkbox"
                                checked={selectedKeys.includes(key)}
                                readOnly
                                aria-label="Seleccionar fila"
                              />
                            </td>
                          )}
                          {visibleFields.map((field, columnIndex) => (
                            <td key={field.name} style={{ textAlign: field.align }} title={getCellText(field, row, filterRemoteOptions[field.name], t('common.yes'), t('common.no'))}>
                              {renderCell(field, row, rowIndex, columnIndex, filterRemoteOptions[field.name], t('common.yes'), t('common.no'))}
                            </td>
                          ))}
                           {hasRowActions && (
                             <td className="native-datagrid__actions-cell" onClick={(e) => e.stopPropagation()}>
                               <div className="native-datagrid__row-actions">
                                 {rowActions.length > 0 && (
                                    <IconButton
                                      icon="ph ph-dots-three-vertical"
                                      label={t('grid.buttonActions')}
                                      onClick={(e) => {
                                       e.stopPropagation();
                                       const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
                                       const rowKey = key;

                                        // Resolve actions at click time (avoids fragile re-lookup later)
                                        const actionsAtOpen: ResourceToolbarAction[] = [
                                          ...(options.allowEdit && (options.onEdit || options.events?.EDIT)
                                            ? [{
                                                text: t('grid.buttonEdit'),
                                                icon: 'ph-pencil-simple',
                                                disabled: options.editDisabled,
                                                onClick: () => {
                                                  if (options.onEdit) options.onEdit(row);
                                                  else emit(options.events!.EDIT!, { row });
                                                },
                                             }]
                                            : []),
                                          ...getResolvedRowActions(row, options.rowActions),
                                          ...(options.allowDelete && (options.onDelete || options.events?.DELETE)
                                            ? [{
                                                text: t('grid.buttonDelete'),
                                                icon: 'ph-trash',
                                                type: 'danger' as const,
                                                disabled: options.deleteDisabled,
                                                onClick: () => setConfirmRow(row),
                                              }]
                                            : []),
                                       ].filter((a) => isToolbarActionVisible(a, toolbarPermissions));

                                       setOpenRowMenu((current) =>
                                         current && current.key === rowKey
                                           ? null
                                           : { key: rowKey, rect, actions: actionsAtOpen }
                                       );
                                      }}
                                    />
                                 )}
                               </div>
                             </td>
                           )}
                        </tr>
                        {expanded && detailUrl && detailFields && (
                          <tr className="native-datagrid__detail-row">
                            <td colSpan={colSpan}>
                              <DetailRows fields={detailFields} url={detailUrl} />
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })
                )}
              </tbody>
              <SummaryFooter
                fields={visibleFields}
                hasCheckbox={hasCheckbox}
                hasDetail={Boolean(options.detailFields)}
                hasRowActions={hasRowActions}
                rows={rows}
                summaryFields={options.summaryFields}
              />
            </table>
            {rows.length === 0 && !isGridLoading && (
              <div className="native-datagrid__empty" aria-live="polite">
                <i className="ph ph-database" aria-hidden="true" />
                <span>{t('grid.noRecords')}</span>
              </div>
            )}
          </div>
          {(options.paging ?? true) && (
            <div className="native-datagrid__pager">
              <span className="native-datagrid__pager-info">
                {totalCount > 0 ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, totalCount)} de ${totalCount}` : '0 registros'}
              </span>
              <div className="native-datagrid__pager-nav">
                <button type="button" className="native-datagrid__pager-btn" onClick={() => setPage(0)} disabled={page === 0} aria-label="Primera página">
                  <i className="ph ph-caret-double-left" aria-hidden="true" />
                </button>
                <button type="button" className="native-datagrid__pager-btn" onClick={() => setPage((p) => Math.max(0, p - 1))} disabled={page === 0} aria-label="Página anterior">
                  <i className="ph ph-caret-left" aria-hidden="true" />
                </button>
                {getPageRange(page, totalPages).map((pageNum, i) =>
                  pageNum === null ? (
                    <span key={`ellipsis-${i}`} className="native-datagrid__pager-ellipsis">…</span>
                  ) : (
                    <button
                      key={pageNum}
                      type="button"
                      className={`native-datagrid__pager-btn${pageNum === page ? ' is-current' : ''}`}
                      onClick={() => setPage(pageNum)}
                      aria-current={pageNum === page ? 'page' : undefined}
                    >
                      {pageNum + 1}
                    </button>
                  )
                )}
                <button type="button" className="native-datagrid__pager-btn" onClick={() => setPage((p) => Math.min(totalPages - 1, p + 1))} disabled={page + 1 >= totalPages} aria-label="Página siguiente">
                  <i className="ph ph-caret-right" aria-hidden="true" />
                </button>
                <button type="button" className="native-datagrid__pager-btn" onClick={() => setPage(totalPages - 1)} disabled={page + 1 >= totalPages} aria-label="Última página">
                  <i className="ph ph-caret-double-right" aria-hidden="true" />
                </button>
              </div>
              <div className="native-datagrid__pager-size">
                <label className="native-datagrid__pager-size-label" htmlFor="native-datagrid-page-size">{t('grid.rowsPerPage')}</label>
                <select
                  id="native-datagrid-page-size"
                  className="native-datagrid__pager-size-select"
                  value={pageSize}
                  onChange={(e) => { setPageSize(Number(e.target.value)); setPage(0); }}
                >
                  {PAGE_SIZE_OPTIONS.map((n) => <option key={n} value={n}>{n}</option>)}
                </select>
              </div>
            </div>
          )}
           {confirmRow && (
            <div className="native-datagrid__confirm-overlay" role="alertdialog" aria-modal="true">
              <div className="native-datagrid__confirm-card">
                <p className="native-datagrid__confirm-title">{t('dialog.confirmDeleteTitle')}</p>
                <p className="native-datagrid__confirm-body">{t('dialog.confirmDelete')}</p>
                <div className="native-datagrid__confirm-actions">
                  <Button variant="secondary" size="sm" onClick={() => setConfirmRow(null)}>
                    {t('dialog.buttonCancel')}
                  </Button>
                  <Button variant="danger" size="sm" onClick={confirmDelete}>
                    {t('grid.buttonDelete')}
                  </Button>
                </div>
              </div>
            </div>
          )}
           {isGridLoading && rows.length > 0 && (
             <div className="native-datagrid__loading-overlay" aria-live="polite">
               <div className="native-datagrid__loading-card">{loadingMessage || t('grid.loading')}</div>
             </div>
           )}

            {openRowMenu && createPortal(
              <div
                className="native-datagrid__row-actions-popover"
                role="menu"
                 style={{
                   position: 'fixed',
                   // Anchor the right edge of the menu to the right edge of the ⋮ button.
                   // This makes the menu open to the left, which is what we want when the
                   // actions column is the last column on the right of a wide table.
                   right: `${Math.max(0, window.innerWidth - openRowMenu.rect.right + 4)}px`,
                   top: (() => {
                     const menuHeightEstimate = 180;
                     const wouldOverflowBottom = openRowMenu.rect.bottom + menuHeightEstimate > window.innerHeight;
                     if (wouldOverflowBottom) {
                       return `${openRowMenu.rect.top - menuHeightEstimate - 8}px`;
                     }
                     return `${openRowMenu.rect.bottom + 4}px`;
                   })(),
                   zIndex: 9999,
                 }}
                onClick={(e) => e.stopPropagation()}
              >
               {(() => {
                 const regular = openRowMenu.actions.filter((a) => a.type !== 'danger');
                 const danger = openRowMenu.actions.filter((a) => a.type === 'danger');

                 return (
                   <>
                     {regular.map((action, idx) => renderRowActionItem(action, idx, toolbarPermissions))}
                     {danger.length > 0 && (
                       <div className="native-datagrid__actions-danger-group">
                         {danger.map((action, idx) => renderRowActionItem(action, idx, toolbarPermissions))}
                       </div>
                     )}
                   </>
                 );
               })()}
             </div>,
             document.body
           )}
         </div>
       </div>
     </div>
   );
 });

NativeDataGridView.displayName = 'NativeDataGridView';
