import './BankReconciliationScreen.scss';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Badge, Button, Card, CollapsibleSection, EmptyState, IconButton, Skeleton } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl, DateUtils } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import bankReconciliationService, {
  type BankStatementLineSuggestion,
  type UnreconciledMovement,
  type UnreconciledMovementsResponse,
} from './services/bankReconciliationService';
import { bankStatementLineApiUrl } from './model/constants';

const API = getCoreApiBaseUrl();

interface BankStatementLine {
  id: string;
  transactionDate: string;
  amount: string;
  glosa: string;
  reference: string | null;
  status: 'UNMATCHED' | 'MATCHED' | 'IGNORED';
}

type BankStatementLineCollection =
  | BankStatementLine[]
  | { ['hydra:member']?: BankStatementLine[]; member?: BankStatementLine[] };

type MovementGroup = 'supplierPayments' | 'salePayments' | 'cashMovements';

const MOVEMENT_GROUP_LABELS: Record<MovementGroup, string> = {
  supplierPayments: 'Pagos a proveedor',
  salePayments: 'Cobros',
  cashMovements: 'Movimientos de caja',
};

const MOVEMENT_GROUPS = Object.keys(MOVEMENT_GROUP_LABELS) as MovementGroup[];

const sourceTypeByGroup: Record<MovementGroup, BankStatementLineSuggestion['sourceType']> = {
  supplierPayments: 'supplier_payment',
  salePayments: 'sale_payment',
  cashMovements: 'cash_movement',
};

const formatAmount = (value: string) => NumberUtils.formatCurrency(Number(value));
const formatDate = (value: string) => DateUtils.format(value);

export const BankReconciliationScreen = () => {
  const navigate = useNavigate();
  const { importId } = useParams<{ importId?: string }>();

  const [bankAccountId, setBankAccountId] = useState<string | null>(null);
  const [lines, setLines] = useState<BankStatementLine[]>([]);
  const [movements, setMovements] = useState<UnreconciledMovementsResponse>({
    supplierPayments: [],
    salePayments: [],
    cashMovements: [],
  });
  const [selectedLineId, setSelectedLineId] = useState<string | null>(null);
  const [suggestions, setSuggestions] = useState<BankStatementLineSuggestion[]>([]);
  const [loading, setLoading] = useState(false);
  const [openGroups, setOpenGroups] = useState<Record<MovementGroup, boolean>>({
    supplierPayments: true,
    salePayments: true,
    cashMovements: true,
  });

  const loadLines = useCallback(() => {
    // DataGridFilter only understands the `filter`/`sort`/`searchValue` grid
    // contract — a bare `?status=X` query param is silently ignored, so the
    // status/account/import scoping has to go through the tuple grammar.
    const filter: Array<[string, string, string]> = [['status', '=', 'UNMATCHED']];
    if (importId) {
      filter.push(['importBatch', '=', `${API}bank-statement-imports/${importId}`]);
    } else if (bankAccountId) {
      filter.push(['bankAccount', '=', `${API}bank-accounts/${bankAccountId}`]);
    }

    return httpClient
      .get<BankStatementLineCollection>(bankStatementLineApiUrl, { params: { filter } })
      .then((response) => {
        const collection = response.data;
        const member = Array.isArray(collection)
          ? collection
          : (collection['hydra:member'] ?? collection.member ?? []);
        setLines(member);
      });
  }, [importId, bankAccountId]);

  const loadMovements = useCallback(() => {
    return bankReconciliationService()
      .unreconciledMovements()
      .then((response) => setMovements(response.data));
  }, []);

  const refetchAll = useCallback(() => {
    setLoading(true);
    Promise.all([loadLines(), loadMovements()]).finally(() => setLoading(false));
  }, [loadLines, loadMovements]);

  useEffect(() => {
    refetchAll();
  }, [refetchAll]);

  const clearSelection = () => {
    setSelectedLineId(null);
    setSuggestions([]);
  };

  const openSuggestions = (lineId: string) => {
    if (selectedLineId === lineId) {
      clearSelection();
      return;
    }
    setSelectedLineId(lineId);
    bankReconciliationService()
      .suggestions(lineId)
      .then((response) => setSuggestions(response.data));
  };

  const confirmMatch = (
    lineId: string,
    sourceType: BankStatementLineSuggestion['sourceType'],
    sourceId: number | string,
  ) => {
    bankReconciliationService()
      .match(lineId, sourceType, sourceId)
      .then(() => {
        toast('Movimiento conciliado', 'success');
        clearSelection();
        refetchAll();
      })
      .catch(() => toast('No se pudo conciliar el movimiento', 'error'));
  };

  const ignoreLine = (lineId: string) => {
    bankReconciliationService()
      .ignore(lineId)
      .then(() => {
        toast('Línea ignorada', 'success');
        if (selectedLineId === lineId) clearSelection();
        refetchAll();
      })
      .catch(() => toast('No se pudo ignorar la línea', 'error'));
  };

  const selectedLine = useMemo(
    () => lines.find((line) => line.id === selectedLineId) ?? null,
    [lines, selectedLineId],
  );

  const totalMovements =
    movements.supplierPayments.length + movements.salePayments.length + movements.cashMovements.length;

  return (
    <div className="bank-reconciliation-page">
      <header className="bank-reconciliation-page__header">
        <div>
          <h1 className="bank-reconciliation-page__title">Conciliación bancaria</h1>
          <p className="bank-reconciliation-page__subtitle">
            Concilia las líneas de tu extracto bancario con los pagos y movimientos ya registrados en el sistema.
          </p>
        </div>
        <div className="bank-reconciliation-page__actions">
          <Button variant="secondary" size="sm" icon="ph ph-bank" onClick={() => navigate('/bank-accounts')}>
            Cuentas bancarias
          </Button>
          <Button
            variant="secondary"
            size="sm"
            icon="ph ph-upload-simple"
            onClick={() => navigate('/bank-statement-imports')}
          >
            Importar extracto
          </Button>
          <IconButton icon="ph ph-arrows-clockwise" label="Actualizar" onClick={refetchAll} />
        </div>
      </header>

      {selectedLine && (
        <div className="bank-reconciliation-page__context-bar">
          <i className="ph ph-link" aria-hidden="true" />
          <span>
            Conciliando <strong>{selectedLine.glosa}</strong> · {formatAmount(selectedLine.amount)} ·{' '}
            {formatDate(selectedLine.transactionDate)}
          </span>
          <Button variant="ghost" size="sm" onClick={clearSelection}>
            Cancelar selección
          </Button>
        </div>
      )}

      <div className="bank-reconciliation-page__panels">
        <Card
          variant="panel"
          className="bank-reconciliation-page__panel"
          title="Extracto bancario"
          description={
            loading
              ? undefined
              : `${lines.length} línea${lines.length === 1 ? '' : 's'} sin conciliar`
          }
        >
          {!importId && (
            <div className="bank-reconciliation-page__account-filter">
              <EntityDropdown
                url={API + 'bank-accounts'}
                value={bankAccountId}
                onChange={setBankAccountId}
                placeholder="Filtrar por cuenta bancaria"
                searchExpr={['alias']}
              />
            </div>
          )}

          {loading && (
            <div className="bank-reconciliation-page__skeletons">
              <Skeleton variant="rect" height={72} />
              <Skeleton variant="rect" height={72} />
              <Skeleton variant="rect" height={72} />
            </div>
          )}

          {!loading && lines.length === 0 && (
            <EmptyState
              icon="check-circle"
              variant="success"
              title="No hay líneas sin conciliar"
              description={
                bankAccountId || importId
                  ? 'Todas las líneas de este extracto ya están conciliadas.'
                  : 'Selecciona una cuenta bancaria o importa un extracto para comenzar.'
              }
              action={
                !bankAccountId && !importId ? (
                  <Button variant="primary" size="sm" onClick={() => navigate('/bank-statement-imports')}>
                    Importar extracto
                  </Button>
                ) : undefined
              }
            />
          )}

          {!loading && lines.length > 0 && (
            <div className="bank-reconciliation-page__lines">
              {lines.map((line) => {
                const isSelected = selectedLineId === line.id;
                const isDebit = Number(line.amount) < 0;

                return (
                  <div
                    key={line.id}
                    className={`br-line${isSelected ? ' br-line--selected' : ''}`}
                  >
                    <div className="br-line__main">
                      <div className="br-line__info">
                        <span className="br-line__date">{formatDate(line.transactionDate)}</span>
                        <span className="br-line__glosa">{line.glosa}</span>
                        {line.reference && <span className="br-line__reference">Ref: {line.reference}</span>}
                      </div>
                      <span className={`br-line__amount${isDebit ? ' is-debit' : ' is-credit'}`}>
                        {formatAmount(line.amount)}
                      </span>
                    </div>

                    <div className="br-line__actions">
                      <Button
                        variant={isSelected ? 'primary' : 'secondary'}
                        size="sm"
                        onClick={() => openSuggestions(line.id)}
                      >
                        {isSelected ? 'Ocultar sugerencias' : 'Ver sugerencias'}
                      </Button>
                      <Button variant="ghost" size="sm" onClick={() => ignoreLine(line.id)}>
                        Ignorar
                      </Button>
                    </div>

                    {isSelected && suggestions.length > 0 && (
                      <div className="br-line__suggestions">
                        <div className="br-line__suggestions-heading">Sugerencias</div>
                        {suggestions.map((s, i) => (
                          <div key={i} className="br-suggestion">
                            <div className="br-suggestion__info">
                              <span className="br-suggestion__label">{s.label}</span>
                              <span className="br-suggestion__date">{formatDate(s.date)}</span>
                            </div>
                            <span className="br-suggestion__amount">{formatAmount(s.amount)}</span>
                            <Badge variant={s.confidence === 'EXACT' ? 'success' : 'warning'} outlined>
                              {s.confidence === 'EXACT' ? 'Exacto' : 'Aproximado'}
                            </Badge>
                            <Button
                              variant="success"
                              size="sm"
                              onClick={() => confirmMatch(line.id, s.sourceType, s.sourceId)}
                            >
                              Conciliar
                            </Button>
                          </div>
                        ))}
                      </div>
                    )}

                    {isSelected && suggestions.length === 0 && (
                      <p className="br-line__no-suggestions">
                        Sin sugerencias automáticas — elige un movimiento del panel de la derecha.
                      </p>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </Card>

        <Card
          variant="panel"
          className="bank-reconciliation-page__panel"
          title="Movimientos del sistema"
          description={loading ? undefined : `${totalMovements} movimiento${totalMovements === 1 ? '' : 's'} sin conciliar`}
        >
          {loading && (
            <div className="bank-reconciliation-page__skeletons">
              <Skeleton variant="rect" height={48} />
              <Skeleton variant="rect" height={48} />
              <Skeleton variant="rect" height={48} />
            </div>
          )}

          {!loading && totalMovements === 0 && (
            <EmptyState
              icon="check-circle"
              variant="success"
              title="Todo conciliado"
              description="No hay pagos ni movimientos de caja pendientes de conciliar."
            />
          )}

          {!loading && totalMovements > 0 && (
            <div className="bank-reconciliation-page__groups">
              {MOVEMENT_GROUPS.map((group) => {
                const items = movements[group];

                return (
                  <CollapsibleSection
                    key={group}
                    label={MOVEMENT_GROUP_LABELS[group]}
                    open={openGroups[group]}
                    onToggle={() => setOpenGroups((prev) => ({ ...prev, [group]: !prev[group] }))}
                    trailing={
                      <Badge variant={items.length > 0 ? 'primary' : 'secondary'} size="sm" outlined>
                        {items.length}
                      </Badge>
                    }
                  >
                    {items.length === 0 ? (
                      <p className="br-movement-group__empty">Sin movimientos.</p>
                    ) : (
                      items.map((m: UnreconciledMovement) => (
                        <div key={m.id} className="br-movement">
                          <div className="br-movement__info">
                            <span className="br-movement__label">{m.label}</span>
                            <span className="br-movement__date">{m.date ? formatDate(m.date) : 'Sin fecha'}</span>
                          </div>
                          <span className="br-movement__amount">{formatAmount(m.amount)}</span>
                          <Button
                            variant="success"
                            size="sm"
                            disabled={!selectedLineId}
                            title={
                              selectedLineId
                                ? undefined
                                : 'Selecciona una línea del extracto a la izquierda primero'
                            }
                            onClick={() =>
                              selectedLineId && confirmMatch(selectedLineId, sourceTypeByGroup[group], m.id)
                            }
                          >
                            Conciliar
                          </Button>
                        </div>
                      ))
                    )}
                  </CollapsibleSection>
                );
              })}
            </div>
          )}
        </Card>
      </div>
    </div>
  );
};
