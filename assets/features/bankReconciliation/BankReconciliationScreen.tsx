import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Badge, Button, Card } from '@nubitio/ui';
import { EntityDropdown } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
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

interface BankStatementLineCollection {
  ['hydra:member']?: BankStatementLine[];
  member?: BankStatementLine[];
}

type MovementGroup = 'supplierPayments' | 'salePayments' | 'cashMovements';

const MOVEMENT_GROUP_LABELS: Record<MovementGroup, string> = {
  supplierPayments: 'Pagos a proveedor',
  salePayments: 'Cobros',
  cashMovements: 'Movimientos de caja',
};

const sourceTypeByGroup: Record<MovementGroup, BankStatementLineSuggestion['sourceType']> = {
  supplierPayments: 'supplier_payment',
  salePayments: 'sale_payment',
  cashMovements: 'cash_movement',
};

export const BankReconciliationScreen = () => {
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

  const loadLines = useCallback(() => {
    const params: Record<string, string> = { status: 'UNMATCHED' };
    if (importId) {
      params.importBatch = `${API}bank-statement-imports/${importId}`;
    }

    return httpClient
      .get<BankStatementLineCollection>(bankStatementLineApiUrl, { params })
      .then((response) => {
        const collection = response.data;
        const member = collection['hydra:member'] ?? collection.member ?? [];
        setLines(member);
      });
  }, [importId]);

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

  const openSuggestions = (lineId: string) => {
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
        setSelectedLineId(null);
        setSuggestions([]);
        refetchAll();
      })
      .catch(() => toast('No se pudo conciliar el movimiento', 'error'));
  };

  const ignoreLine = (lineId: string) => {
    bankReconciliationService()
      .ignore(lineId)
      .then(() => {
        toast('Línea ignorada', 'success');
        refetchAll();
      })
      .catch(() => toast('No se pudo ignorar la línea', 'error'));
  };

  return (
    <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start' }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <Card title="Extracto bancario">
          {!importId && (
            <div className="mb-3">
              <EntityDropdown
                url={API + 'bank-accounts'}
                value={bankAccountId}
                onChange={setBankAccountId}
                placeholder="Filtrar por cuenta bancaria"
                searchExpr={['alias']}
              />
            </div>
          )}

          {loading && <p>Cargando…</p>}

          {!loading && lines.length === 0 && <p>No hay líneas sin conciliar.</p>}

          <div style={{ maxHeight: 600, overflowY: 'auto' }}>
            {lines.map((line) => (
            <div
              key={line.id}
              style={{
                border:
                  selectedLineId === line.id
                    ? '2px solid var(--primary-color)'
                    : '1px solid var(--border-color)',
                borderRadius: 8,
                padding: 12,
                marginBottom: 8,
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <strong>{line.transactionDate}</strong>
                <span>{line.amount}</span>
              </div>
              <div>{line.glosa}</div>
              {line.reference && (
                <div style={{ color: 'var(--text-muted)' }}>Ref: {line.reference}</div>
              )}
              <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                <Button variant="primary" size="sm" onClick={() => openSuggestions(line.id)}>
                  Ver sugerencias
                </Button>
                <Button variant="secondary" size="sm" onClick={() => ignoreLine(line.id)}>
                  Ignorar
                </Button>
              </div>

              {selectedLineId === line.id && suggestions.length > 0 && (
                <div style={{ marginTop: 8 }}>
                  {suggestions.map((s, i) => (
                    <div
                      key={i}
                      style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        padding: 4,
                      }}
                    >
                      <span>
                        {s.label} — {s.amount} ({s.date})
                      </span>
                      <span style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
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
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
            ))}
          </div>
        </Card>
      </div>

      <div style={{ flex: 1, minWidth: 0 }}>
        <Card title="Movimientos del sistema sin conciliar">
          <div style={{ maxHeight: 600, overflowY: 'auto' }}>
            {(Object.keys(MOVEMENT_GROUP_LABELS) as MovementGroup[]).map((group) => (
              <div key={group} style={{ marginBottom: 16 }}>
                <h4>{MOVEMENT_GROUP_LABELS[group]}</h4>
                {movements[group].length === 0 && (
                  <p style={{ color: 'var(--text-muted)' }}>Sin movimientos.</p>
                )}
                {movements[group].map((m: UnreconciledMovement) => (
                  <div
                    key={m.id}
                    style={{ display: 'flex', justifyContent: 'space-between', padding: 4 }}
                  >
                    <span>
                      {m.label} — {m.amount} ({m.date ?? 's/f'})
                    </span>
                    {selectedLineId && (
                      <Button
                        variant="success"
                        size="sm"
                        onClick={() => confirmMatch(selectedLineId, sourceTypeByGroup[group], m.id)}
                      >
                        Conciliar
                      </Button>
                    )}
                  </div>
                ))}
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
};
