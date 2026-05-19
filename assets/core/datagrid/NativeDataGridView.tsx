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
import { useNavigate } from 'react-router-dom';
import { ApiPlatformRemoteDataSource, type RemoteLoadOptions } from '@core/data/ApiPlatformRemoteDataSource';
import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import { FieldType } from '@core/field/FieldType';
import { useEvents } from '@core/event/EventHook';
import { DATA_GRID_EVENTS } from './DataGridEvents';
import type { DataGridSummaryItem, DataGridViewOptions } from './DataGridViewOptions';
import type { GridHandle } from './GridHandle';
import type { FilterRule } from '@core/field/FilterRule';
import type { ToolbarButtonItem } from '@core/event/EventTypes';
import toast from '@shared/lib/SharedUtils';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';

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

function getVisibleFields(options: DataGridViewOptions): Field[] {
  return options.fields
    .filter((field) => field.visible && !field.hidden)
    .filter((field) => options.visibleColumns == null || options.visibleColumns.includes(field.name))
    .sort((a, b) => (a.order ?? Number.MAX_SAFE_INTEGER) - (b.order ?? Number.MAX_SAFE_INTEGER));
}

function getPrimitiveDisplay(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (value instanceof Date) return value.toLocaleDateString();
  if (typeof value === 'boolean') return value ? 'Si' : 'No';
  if (typeof value === 'object') return '';
  return String(value);
}

function getEntityDisplayValue(field: Field, value: unknown): string {
  if (value && typeof value === 'object') {
    const record = value as DataRecord;
    const display = record[field.textField] ?? record['name'] ?? record['businessName'];
    return getPrimitiveDisplay(display);
  }
  return getPrimitiveDisplay(value);
}

function getEnumDisplayValue(field: Field, value: unknown): string {
  const match = Array.isArray(field.data)
    ? field.data.find((item) => item['value'] === value || item[field.valueField] === value)
    : undefined;

  return getPrimitiveDisplay(match?.['text'] ?? match?.[field.textField] ?? value);
}

function getCellText(field: Field, row: DataRecord): string {
  if (field.formatter) return '';
  const value = row[field.name];
  if (field.type === FieldType.ENTITY) return getEntityDisplayValue(field, value);
  if (field.type === FieldType.ENUM || field.type === FieldType.SWITCH) return getEnumDisplayValue(field, value);
  return getPrimitiveDisplay(value);
}

function renderCell(field: Field, row: DataRecord, rowIndex: number, columnIndex: number) {
  const value = row[field.name];
  if (field.formatter) {
    return field.formatter({ value, data: row, rowIndex, columnIndex });
  }
  if (field.type === FieldType.ENTITY) return getEntityDisplayValue(field, value);
  if (field.type === FieldType.ENUM || field.type === FieldType.SWITCH) {
    return getEnumDisplayValue(field, value);
  }
  return getPrimitiveDisplay(value);
}

function buildToolbarButtons(items: (ToolbarButtonItem | ToolbarButtonItem[])[] = []) {
  return items.flatMap((item, index) => {
    const buttonItems = Array.isArray(item) ? item : [item];
    return buttonItems.map((button, subIndex) => {
      if (button.visible === false) return null;
      return (
        <button
          key={`${index}-${subIndex}`}
          type="button"
          className="app-button app-button--secondary app-button--sm"
          onClick={button.onClick}
          disabled={button.disabled}
          title={button.hint}
          aria-label={button.hint ?? button.text}
        >
          {button.icon && <i className={`ph ${button.icon}`} aria-hidden="true" />}
          {button.text && <span>{button.text}</span>}
        </button>
      );
    });
  });
}

type FilterOperator = { value: string; label: string };

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
        { value: '>', label: '>' },
        { value: '>=', label: '≥' },
        { value: '<', label: '<' },
        { value: '<=', label: '≤' },
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

function buildFilterExpression(filters: Record<string, string>, operators: Record<string, string>): unknown[] {
  return Object.entries(filters)
    .filter(([, value]) => value.trim() !== '')
    .map(([fieldName, value]) => [fieldName, operators[fieldName] ?? 'contains', value]);
}

function FilterCell({
  field,
  operator,
  remoteOptions,
  value,
  onClear,
  onInputChange,
  onSelectChange,
  onOperatorChange,
}: {
  field: Field;
  operator: string;
  remoteOptions: DataRecord[];
  value: string;
  onClear: () => void;
  onInputChange: (value: string) => void;
  onSelectChange: (value: string) => void;
  onOperatorChange: (operator: string) => void;
}) {
  const isDropdown =
    field.type === FieldType.ENUM ||
    field.type === FieldType.SELECT ||
    field.type === FieldType.SWITCH ||
    field.type === FieldType.ENTITY;

  if (isDropdown) {
    const isEntity = field.type === FieldType.ENTITY;
    const items = isEntity ? remoteOptions : (field.data ?? []);
    return (
      <select
        className="native-datagrid__filter-select"
        value={value}
        aria-label={`Filtrar ${field.label}`}
        onChange={(e) => onSelectChange(e.target.value)}
      >
        <option value="">Todos</option>
        {isEntity
          ? items.map((item) => {
              const v = field.valueField === '_iri'
                ? String(item['_iri'] ?? item['@id'] ?? item['id'] ?? '')
                : String(item[field.valueField] ?? item['id'] ?? '');
              const t = String(item[field.textField] ?? item['name'] ?? item['businessName'] ?? '');
              return <option key={v} value={v}>{t}</option>;
            })
          : items.map((item) => (
              <option key={String(item['value'] ?? '')} value={String(item['value'] ?? '')}>
                {String(item['text'] ?? item['value'] ?? '')}
              </option>
            ))}
      </select>
    );
  }

  const operators = getFilterOperators(field);
  const inputType =
    field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY ? 'number' :
    field.type === FieldType.DATE ? 'date' :
    field.type === FieldType.DATETIME ? 'datetime-local' : 'text';

  return (
    <div className="native-datagrid__filter-wrap">
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
      <input
        type={inputType}
        className="native-datagrid__filter-input"
        value={value}
        aria-label={`Filtrar ${field.label}`}
        onChange={(e) => onInputChange(e.target.value)}
      />
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

function SummaryView({ summaryFields, rows }: { summaryFields?: DataGridSummaryItem[]; rows: DataRecord[] }) {
  if (!summaryFields?.length) return null;

  return (
    <div className="native-datagrid__summary">
      {summaryFields.map((summary, index) => {
        const values = rows.map((row) => Number(row[summary.column ?? ''] ?? 0));
        const value =
          summary.summaryType === 'sum'
            ? values.reduce((total, next) => total + (Number.isFinite(next) ? next : 0), 0)
            : values.length;
        const text = summary.customizeText
          ? summary.customizeText({ value, valueText: String(value) })
          : summary.displayFormat
            ? summary.displayFormat.replace('{0}', String(value))
            : String(value);

        return <span key={index}>{text}</span>;
      })}
    </div>
  );
}

function DetailRows({ fields, url }: { fields: Field[]; url: string }) {
  const [rows, setRows] = useState<DataRecord[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const source = new ApiPlatformRemoteDataSource({ url, idField: getIdField(fields) });
    setLoading(true);
    source
      .load({})
      .then((result) => setRows(result.data))
      .finally(() => setLoading(false));
  }, [fields, url]);

  if (loading) return <div className="native-datagrid__loading">Cargando...</div>;
  if (rows.length === 0) return <div className="native-datagrid__empty">Sin detalle</div>;

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
                <td key={field.name}>{renderCell(field, row, rowIndex, columnIndex)}</td>
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
  const navigate = useNavigate();
  const [on, emit] = useEvents();
  const idField = useMemo(() => getIdField(options.fields), [options.fields]);
  const visibleFields = useMemo(() => getVisibleFields(options), [options]);
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
  const onContentReadyRef = useRef(options.onContentReady);

  useEffect(() => {
    onContentReadyRef.current = options.onContentReady;
  }, [options.onContentReady]);

  const source = useMemo(
    () =>
      new ApiPlatformRemoteDataSource({
        url: options.url,
        idField,
        defaultFilterRules: options.filter as unknown[][] | undefined,
        defaultSortRules: sort,
      }),
    [idField, options.filter, options.url, sort],
  );

  const loadRows = useCallback(async () => {
    if (options.manualLoad) return rowsRef.current;
    setIsGridLoading(true);
    const loadOptions: RemoteLoadOptions = {
      filter: buildFilterExpression(filters, filterOperators),
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
  }, [filterOperators, filters, options.manualLoad, options.paging, page, pageSize, sort, source]);

  useEffect(() => {
    void loadRows();
  }, [loadRows]);

  useEffect(() => {
    if (options.events?.SUCCESS) on(options.events.SUCCESS, () => void loadRows());
  }, [loadRows, on, options.events]);

  useEffect(() => {
    visibleFields
      .filter((f) => f.filterable && f.type === FieldType.ENTITY && f.url && !loadedFilterOptionsRef.current.has(f.name))
      .forEach((f) => {
        loadedFilterOptionsRef.current.add(f.name);
        new ApiPlatformRemoteDataSource({ url: f.url!, idField: f.valueField })
          .load({ take: 500 })
          .then((result) => setFilterRemoteOptions((prev) => ({ ...prev, [f.name]: result.data })))
          .catch(() => {});
      });
  }, [visibleFields]);

  useImperativeHandle(ref, () => ({
    showLoading: (message) => {
      setLoadingMessage(message ?? t('grid.loading'));
      setIsGridLoading(true);
    },
    hideLoading: () => setIsGridLoading(false),
    refresh: () => void loadRows(),
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
      const key = selectedKeys[0];
      return typeof key === 'string' || typeof key === 'number' ? key : undefined;
    },
    getSelectedRow: () => rowsRef.current.find((row) => row[idField] === selectedKeys[0]),
    getSelectedRowKeys: () => selectedKeys,
    getSelectedRows: () => rowsRef.current.filter((row) => selectedKeys.includes(row[idField])),
    getFilter: () => buildFilterExpression(filters, filterOperators),
    filter: (filterRule: FilterRule | null) => {
      const nextFilters = filterRule ? { [filterRule.field]: String(filterRule.value ?? '') } : {};
      const nextOperators = filterRule
        ? { ...filterOperators, [filterRule.field]: filterRule.operator || getDefaultFilterOperator(options.fields.find((f) => f.name === filterRule.field) ?? options.fields[0]) }
        : computeDefaultOperators(options.fields);
      clearTimeout(filterDebounceRef.current);
      setFilters(nextFilters);
      setFilterInputs(nextFilters);
      setFilterOperators(nextOperators);
      setPage(0);
    },
  }));

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

  const onAddClick = () => options.events?.ADD && emit(options.events.ADD);
  const onEditClick = () => {
    const row = selectedRows[0];
    if (row) {
      if (options.events?.EDIT) emit(options.events.EDIT, { row });
    } else {
      toast(t('grid.selectRequired'), 'warning', 2000);
    }
  };
  const onDeleteClick = () => {
    const row = selectedRows[0];
    if (row) {
      setConfirmRow(row);
    } else {
      toast(t('grid.selectRequired'), 'warning', 2000);
    }
  };

  const confirmDelete = () => {
    if (confirmRow && options.events?.DELETE) emit(options.events.DELETE, { row: confirmRow });
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

  const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
  const hasCheckbox = options.selectionMode === 'multiple';
  const hasRowActions = Boolean(options.inlineActions && (options.allowEdit || options.allowDelete) && options.mode !== 'minimal');
  const colSpan = visibleFields.length + (hasCheckbox ? 1 : 0) + (options.detailFields ? 1 : 0) + (hasRowActions ? 1 : 0);

  return (
    <div className="view datagrid-list">
      <div className={`view-wrapper ${options.mode === 'full' || !options.mode ? 'view-wrapper-datagrid-list list-page' : ''}`}>
        <div className="grid theme-dependent native-datagrid">
          {(options.toolbarVisible ?? true) && options.mode !== 'minimal' && (
            <div className="native-datagrid__toolbar">
              {options.hasBackButton && (
                <button
                  type="button"
                  className="app-icon-button"
                  aria-label="Volver"
                  onClick={() => (options.onBack ? options.onBack() : navigate(-1))}
                >
                  <i className="ph ph-arrow-left" aria-hidden="true" />
                </button>
              )}
              {options.mode === 'full' || !options.mode ? <div className="grid-header">{options.title}</div> : null}
              {options.beforeToolbar?.()}
              <div className="native-datagrid__toolbar-spacer" />
              {options.allowAdd && (
                <button type="button" className="app-button app-button--primary app-button--sm" onClick={onAddClick} disabled={options.addDisabled}>
                  {t('grid.buttonNew')}
                </button>
              )}
              {options.allowEdit && (
                <button type="button" className="app-button app-button--secondary app-button--sm" onClick={onEditClick} disabled={options.editDisabled}>
                  {t('grid.buttonEdit')}
                </button>
              )}
              {options.allowDelete && (
                <button type="button" className="app-button app-button--danger app-button--sm" onClick={onDeleteClick} disabled={options.deleteDisabled}>
                  {t('grid.buttonDelete')}
                </button>
              )}
              {buildToolbarButtons(options.toolbar)}
              <button type="button" className="app-icon-button" aria-label={t('grid.buttonRefresh')} onClick={() => void loadRows()}>
                <i className="ph ph-arrow-clockwise" aria-hidden="true" />
              </button>
            </div>
          )}
          <div className="native-datagrid__table-wrap">
            <table className="native-datagrid__table">
              <thead>
                <tr>
                  {options.detailFields && <th className="native-datagrid__detail-cell" />}
                  {hasCheckbox && <th className="native-datagrid__select-cell" />}
                  {visibleFields.map((field) => {
                    const sortRule = sort.find((rule) => rule.selector === field.name);
                    return (
                      <th key={field.name} title={field.label} style={{ width: field.width, minWidth: field.minWidth, textAlign: field.align }}>
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
                      </th>
                    );
                  })}
                  {hasRowActions && <th className="native-datagrid__actions-cell" />}
                </tr>
                {(options.filterRow ?? true) && (
                  <tr className="native-datagrid__filter-row">
                    {options.detailFields && <td />}
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
                      <td key={field.name} style={{ minWidth: field.minWidth }}>
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
                            onSelectChange={(nextValue) => {
                              const nextInputs = { ...filterInputs, [field.name]: nextValue };
                              if (nextValue === '') delete nextInputs[field.name];
                              applyImmediate(nextInputs);
                            }}
                            onOperatorChange={(nextOperator) => {
                              setFilterOperators((prev) => ({ ...prev, [field.name]: nextOperator }));
                              if (filterInputs[field.name]) {
                                applyImmediate({ ...filterInputs });
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
                    {hasRowActions && <td />}
                  </tr>
                )}
              </thead>
              <tbody>
                {rows.length === 0 && isGridLoading ? (
                  Array.from({ length: 7 }, (_, i) => (
                    <tr key={`skeleton-${i}`} className="native-datagrid__skeleton-row">
                      {options.detailFields && <td />}
                      {hasCheckbox && <td className="native-datagrid__select-cell"><div className="native-datagrid__skeleton-cell" style={{ width: 16, height: 16 }} /></td>}
                      {visibleFields.map((field) => (
                        <td key={field.name}><div className="native-datagrid__skeleton-cell" /></td>
                      ))}
                      {hasRowActions && <td />}
                    </tr>
                  ))
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={colSpan}>
                      <div className="native-datagrid__empty">
                        <i className="ph ph-database" aria-hidden="true" />
                        <span>{t('grid.noRecords')}</span>
                      </div>
                    </td>
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
                    options.onRowPrepared?.({ data: row, rowIndex });

                    return (
                      <React.Fragment key={String(key)}>
                        <tr
                          className={`native-datagrid__row ${expanded ? 'native-datagrid__row--expanded' : ''} ${selectedKeys.includes(key) ? 'native-datagrid__row--selected' : ''}`}
                          tabIndex={0}
                          onClick={() => selectRow(row)}
                          onKeyDown={(event) => {
                            if (event.key === 'Enter' && options.events?.EDIT) {
                              emit(options.events.EDIT, { row });
                            } else if (event.key === 'Delete' && options.events?.DELETE) {
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
                            <td key={field.name} style={{ textAlign: field.align }} title={getCellText(field, row)}>
                              {renderCell(field, row, rowIndex, columnIndex)}
                            </td>
                          ))}
                          {hasRowActions && (
                            <td className="native-datagrid__actions-cell" onClick={(e) => e.stopPropagation()}>
                              <div className="native-datagrid__row-actions">
                                {options.allowEdit && options.events?.EDIT && (
                                  <button
                                    type="button"
                                    className="app-icon-button"
                                    aria-label={t('grid.buttonEdit')}
                                    title={t('grid.buttonEdit')}
                                    onClick={(e) => { e.stopPropagation(); emit(options.events!.EDIT!, { row }); }}
                                  >
                                    <i className="ph ph-pencil-simple" aria-hidden="true" />
                                  </button>
                                )}
                                {options.allowDelete && options.events?.DELETE && (
                                  <button
                                    type="button"
                                    className="app-icon-button"
                                    aria-label={t('grid.buttonDelete')}
                                    title={t('grid.buttonDelete')}
                                    onClick={(e) => { e.stopPropagation(); setConfirmRow(row); }}
                                  >
                                    <i className="ph ph-trash" aria-hidden="true" />
                                  </button>
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
            </table>
          </div>
          <SummaryView summaryFields={options.summaryFields} rows={rows} />
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
                  <button type="button" className="app-button app-button--secondary app-button--sm" onClick={() => setConfirmRow(null)}>
                    {t('dialog.buttonCancel')}
                  </button>
                  <button type="button" className="app-button app-button--danger app-button--sm" onClick={confirmDelete}>
                    {t('grid.buttonDelete')}
                  </button>
                </div>
              </div>
            </div>
          )}
          {isGridLoading && rows.length > 0 && (
            <div className="native-datagrid__loading-overlay" aria-live="polite">
              <div className="native-datagrid__loading-card">{loadingMessage || t('grid.loading')}</div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
});

NativeDataGridView.displayName = 'NativeDataGridView';
