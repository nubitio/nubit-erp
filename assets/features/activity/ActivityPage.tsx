import { useEffect, useMemo, useState } from 'react';
import { Alert, Avatar, Badge, Button, SelectField, TextField } from '@nubitio/ui';

import {
  DEFAULT_FILTERS,
  formatTime,
  groupByDay,
  RANGE_OPTIONS,
  TYPE_OPTIONS,
  type ActivityFilters,
  type ActivityRange,
  type ActivityTypeFilter,
} from './activityFormat';
import {
  activityExportUrl,
  useActivityActors,
  useActivityList,
  useActivitySummary,
} from './useActivity';

import './ActivityPage.scss';

const SEARCH_DEBOUNCE_MS = 300;

/** Debounced copy of a value; state is only set from the timer callback, never synchronously. */
function useDebounced<T>(value: T, delay: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(timer);
  }, [value, delay]);
  return debounced;
}

/** "Usuarios y permisos › Actividad": who did what, newest first. Read-only. */
export function ActivityPage() {
  const [filters, setFilters] = useState<ActivityFilters>(DEFAULT_FILTERS);
  const [searchText, setSearchText] = useState('');
  const search = useDebounced(searchText, SEARCH_DEBOUNCE_MS);
  const effective = useMemo(() => ({ ...filters, search }), [filters, search]);

  const list = useActivityList(effective);
  const summary = useActivitySummary();
  const actors = useActivityActors();

  const items = useMemo(() => list.data?.pages.flatMap((page) => page.items) ?? [], [list.data]);
  const groups = useMemo(() => groupByDay(items), [items]);
  const total = list.data?.pages[0]?.total ?? 0;

  const set = <K extends keyof ActivityFilters>(key: K, value: ActivityFilters[K]) =>
    setFilters((current) => ({ ...current, [key]: value }));

  return (
    <div className="activity-page" data-testid="activity-page">
      <div className="activity-filters">
        <TextField
          type="search"
          aria-label="Buscar en la actividad"
          placeholder="Buscar por usuario, rol o palabra"
          value={searchText}
          onChange={(event) => setSearchText(event.target.value)}
        />
        <SelectField
          aria-label="Usuario"
          value={filters.actor}
          onChange={(event) => set('actor', event.target.value)}
        >
          <option value="">Usuario: Todos</option>
          {(actors.data ?? []).map((name) => (
            <option key={name} value={name}>
              {name}
            </option>
          ))}
        </SelectField>
        <SelectField
          aria-label="Tipo"
          value={filters.type}
          onChange={(event) => set('type', event.target.value as ActivityTypeFilter)}
        >
          {TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              Tipo: {option.label}
            </option>
          ))}
        </SelectField>
        <SelectField
          aria-label="Fecha"
          value={filters.range}
          onChange={(event) => set('range', event.target.value as ActivityRange)}
        >
          {RANGE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              Fecha: {option.label}
            </option>
          ))}
        </SelectField>
        <Button
          variant="secondary"
          onClick={() => window.location.assign(activityExportUrl(effective))}
        >
          Exportar
        </Button>
      </div>

      <div className="activity-layout">
        <section className="activity-feed" aria-label="Actividad">
          {list.isError ? (
            <Alert tone="danger">
              No se pudo cargar la actividad.{' '}
              <Button variant="secondary" size="sm" onClick={() => void list.refetch()}>
                Reintentar
              </Button>
            </Alert>
          ) : list.isPending ? (
            <p role="status">Cargando actividad…</p>
          ) : groups.length === 0 ? (
            <p role="status" className="activity-empty" data-testid="activity-empty">
              No hay actividad con estos filtros.
            </p>
          ) : (
            groups.map((group) => (
              <div key={group.key} className="activity-day">
                <h3 className="activity-day__heading">{group.heading}</h3>
                <ul className="activity-list">
                  {group.items.map((entry) => (
                    <li
                      key={entry.id}
                      className="activity-row"
                      data-testid="activity-row"
                      data-type={entry.type}
                    >
                      <time className="activity-row__time" dateTime={entry.occurredAt}>
                        {formatTime(entry.occurredAt)}
                      </time>
                      <Avatar owner={entry.actor ?? entry.summary} size={32} />
                      <div className="activity-row__body">
                        <strong>{entry.summary}</strong>
                        {entry.detail ? <small>{entry.detail}</small> : null}
                      </div>
                      {entry.sensitive ? (
                        <Badge pill variant="danger">
                          Sensible
                        </Badge>
                      ) : null}
                    </li>
                  ))}
                </ul>
              </div>
            ))
          )}

          {list.hasNextPage ? (
            <div className="activity-more">
              <Button
                variant="secondary"
                loading={list.isFetchingNextPage}
                onClick={() => void list.fetchNextPage()}
              >
                Ver más ({total - items.length} restantes)
              </Button>
            </div>
          ) : null}

          <p className="activity-note">
            <i className="ph ph-shield-check" aria-hidden="true" /> Este registro no se puede editar
            ni borrar desde el sistema.
          </p>
        </section>

        <aside className="activity-side" aria-label="Resumen de la semana">
          <h3>Esta semana</h3>
          {summary.isPending ? (
            <p role="status">Cargando…</p>
          ) : (summary.data?.items.length ?? 0) === 0 ? (
            <p>Sin actividad registrada.</p>
          ) : (
            <dl>
              {summary.data?.items.map((entry) => (
                <div key={entry.type} data-testid="activity-summary-row">
                  <dt>{entry.label}</dt>
                  <dd>{entry.count}</dd>
                </div>
              ))}
            </dl>
          )}
        </aside>
      </div>
    </div>
  );
}
