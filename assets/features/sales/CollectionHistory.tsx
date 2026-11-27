import { useCallback, useEffect, useState } from 'react';
import { Badge, Button, ConfirmDialog, Text } from '@nubitio/ui';
import { formatDate, formatMoney } from '@shared/lib/displayFormat';
import toast from '@shared/lib/SharedUtils';
import collectionService, { type CollectionHistoryItem } from './services/collectionService';

interface Props {
  customerId: number;
  /** Undoing a collection needs its own permission; without it the list is read-only. */
  canReverse: boolean;
  /** Called after a reversal so the invoices and the balance reload. */
  onChanged: () => void;
  /** Changes when something else registered a collection, so the list reloads. */
  refreshKey?: number;
}

/** The customer's latest collections, how each arrived, and the action to undo one. */
export const CollectionHistory = ({ customerId, canReverse, onChanged, refreshKey = 0 }: Props) => {
  const [items, setItems] = useState<CollectionHistoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [undo, setUndo] = useState<CollectionHistoryItem | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(
    (signal: AbortSignal) => {
      collectionService
        .history(customerId, signal)
        .then((rows) => {
          if (!signal.aborted) setItems(rows);
        })
        .catch(() => {
          if (!signal.aborted) setError(true);
        })
        .finally(() => {
          if (!signal.aborted) setLoading(false);
        });
    },
    [customerId],
  );

  useEffect(() => {
    const controller = new AbortController();
    load(controller.signal);
    return () => controller.abort();
  }, [load, attempt, refreshKey]);

  const confirmUndo = () => {
    if (!undo || busy) return;
    setBusy(true);
    collectionService
      .reverse(undo.id)
      .then(() => {
        toast('Cobranza revertida', 'success');
        setUndo(null);
        setLoading(true);
        setAttempt((value) => value + 1);
        onChanged();
      })
      .catch(() => {
        /* HttpClient shows why (e.g. it left an advance); keep the dialog open. */
      })
      .finally(() => setBusy(false));
  };

  return (
    <section className="receivable-detail__panel" aria-labelledby="receivable-collections-title">
      <div className="receivable-detail__panel-heading">
        <div>
          <h2 id="receivable-collections-title">Cobranzas</h2>
          <p>Dinero recibido, sin importar a qué comprobantes se aplicó</p>
        </div>
      </div>

      {error ? (
        <Text tone="muted" as="p">
          Historial no disponible.{' '}
          <Button
            variant="secondary"
            size="sm"
            onClick={() => {
              setLoading(true);
              setError(false);
              setAttempt((value) => value + 1);
            }}
          >
            Reintentar
          </Button>
        </Text>
      ) : loading ? (
        <Text tone="muted" as="p">
          Cargando cobranzas…
        </Text>
      ) : items.length === 0 ? (
        <p className="receivable-detail__empty-payments">Todavía no hay cobranzas registradas.</p>
      ) : (
        <ul className="receivable-detail__payments" data-testid="receivable-collections">
          {items.map((item) => (
            <li key={item.id}>
              <div>
                <strong>{item.tenders.map((t) => t.method).join(' + ') || 'Cobranza'}</strong>
                <span>
                  {formatDate(item.receivedOn)}
                  {Number(item.unapplied) > 0
                    ? ` · ${formatMoney(item.unapplied, item.currency)} a favor`
                    : ''}
                </span>
                {item.status === 'reversed' && (
                  <Badge variant="secondary" outlined>
                    Revertida
                  </Badge>
                )}
              </div>
              <div>
                <strong>{formatMoney(item.total, item.currency)}</strong>
                {canReverse && item.status === 'confirmed' && Number(item.unapplied) === 0 && (
                  <Button
                    variant="ghost"
                    size="sm"
                    data-testid={`receivable-reverse-${item.id}`}
                    onClick={() => setUndo(item)}
                  >
                    Revertir
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      <ConfirmDialog
        open={undo !== null}
        title="Revertir cobranza"
        confirmLabel="Confirmar reversión"
        message={
          undo
            ? `Se devolverán ${formatMoney(undo.total, undo.currency)} y los comprobantes volverán a quedar pendientes. El registro original se conserva.`
            : ''
        }
        variant="danger"
        onConfirm={confirmUndo}
        onCancel={() => setUndo(null)}
      />
    </section>
  );
};
