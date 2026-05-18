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

const PAGE_SIZE = 20;

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

function buildFilterExpression(filters: Record<string, string>): unknown[] {
  return Object.entries(filters)
    .filter(([, value]) => value.trim() !== '')
    .map(([field, value]) => [field, 'contains', value]);
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
      <table className="native-datagrid__table">
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
  const [sort, setSort] = useState<SortRule[]>(options.sort ?? []);
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
      filter: buildFilterExpression(filters),
      sort,
    };
    if (options.paging ?? true) {
      loadOptions.skip = page * PAGE_SIZE;
      loadOptions.take = PAGE_SIZE;
    }

    const result = await source.load(loadOptions);
    rowsRef.current = result.data;
    setRows(result.data);
    setTotalCount(result.totalCount);
    setIsGridLoading(false);
    onContentReadyRef.current?.();
    return result.data;
  }, [filters, options.manualLoad, options.paging, page, sort, source]);

  useEffect(() => {
    void loadRows();
  }, [loadRows]);

  useEffect(() => {
    if (options.events?.SUCCESS) on(options.events.SUCCESS, () => void loadRows());
  }, [loadRows, on, options.events]);

  useImperativeHandle(ref, () => ({
    showLoading: (message) => {
      setLoadingMessage(message ?? t('grid.loading'));
      setIsGridLoading(true);
    },
    hideLoading: () => setIsGridLoading(false),
    refresh: () => void loadRows(),
    reset: () => {
      setSelectedKeys([]);
      setFilters({});
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
    getFilter: () => buildFilterExpression(filters),
    filter: (filterRule: FilterRule | null) => {
      setFilters(filterRule ? { [filterRule.field]: String(filterRule.value ?? '') } : {});
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
      if (options.events?.DELETE) emit(options.events.DELETE, { row });
    } else {
      toast(t('grid.selectRequired'), 'warning', 2000);
    }
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

  const totalPages = Math.max(1, Math.ceil(totalCount / PAGE_SIZE));
  const colSpan = visibleFields.length + 1 + (options.detailFields ? 1 : 0);

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
                  <th className="native-datagrid__select-cell" />
                  {visibleFields.map((field) => {
                    const sortRule = sort.find((rule) => rule.selector === field.name);
                    return (
                      <th key={field.name} style={{ width: field.width, minWidth: field.minWidth, textAlign: field.align }}>
                        <button type="button" className="native-datagrid__sort-button" onClick={() => toggleSort(field)}>
                          <span>{field.label}</span>
                          {sortRule && <i className={`ph ${sortRule.desc ? 'ph-arrow-down' : 'ph-arrow-up'}`} aria-hidden="true" />}
                        </button>
                        {(options.filterRow ?? true) && field.filterable && (
                          <div className="native-datagrid__filter">
                            <input
                              value={filters[field.name] ?? ''}
                              aria-label={`Filtrar ${field.label}`}
                              onChange={(event) => {
                                const nextFilters = { ...filters, [field.name]: event.target.value };
                                if (event.target.value === '') delete nextFilters[field.name];
                                setFilters(nextFilters);
                                setPage(0);
                                options.onFilterChange?.(nextFilters);
                              }}
                            />
                          </div>
                        )}
                      </th>
                    );
                  })}
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 && !isGridLoading ? (
                  <tr>
                    <td colSpan={colSpan} className="native-datagrid__empty">{t('grid.noRecords')}</td>
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
                        <tr className={`native-datagrid__row ${selectedKeys.includes(key) ? 'native-datagrid__row--selected' : ''}`} onClick={() => selectRow(row)}>
                          {options.detailFields && (
                            <td className="native-datagrid__detail-cell">
                              {detailUrl && detailFields && (
                                <button
                                  type="button"
                                  className="app-icon-button"
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
                          <td className="native-datagrid__select-cell">
                            <input
                              type="checkbox"
                              checked={selectedKeys.includes(key)}
                              readOnly
                              aria-label="Seleccionar fila"
                            />
                          </td>
                          {visibleFields.map((field, columnIndex) => (
                            <td key={field.name} style={{ textAlign: field.align }}>
                              {renderCell(field, row, rowIndex, columnIndex)}
                            </td>
                          ))}
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
              <button type="button" className="app-button app-button--secondary app-button--sm" onClick={() => setPage((value) => Math.max(0, value - 1))} disabled={page === 0}>
                Anterior
              </button>
              <span>Página {page + 1} de {totalPages}</span>
              <button type="button" className="app-button app-button--secondary app-button--sm" onClick={() => setPage((value) => Math.min(totalPages - 1, value + 1))} disabled={page + 1 >= totalPages}>
                Siguiente
              </button>
            </div>
          )}
          {isGridLoading && (
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
