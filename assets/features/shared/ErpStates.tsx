import type { ReactNode } from 'react';
import { EmptyState } from '@nubitio/ui';
import { Button } from '@nubitio/ui';

interface RetryProps {
  onRetry?: () => void;
}

interface DraftProps extends RetryProps {
  onSaveAsDraft?: () => void;
}

/** SUNAT service unreachable — network or OSE timeout. */
export function SunatUnavailableState({ onRetry, onSaveAsDraft }: DraftProps) {
  return (
    <EmptyState
      variant="warning"
      icon="warning-circle"
      title="No se pudo conectar con SUNAT"
      description="El servicio de SUNAT no responde. Reintenta o guarda el comprobante como borrador para enviarlo más tarde."
      action={
        <>
          {onRetry && (
            <Button variant="primary" icon="ph ph-arrow-clockwise" onClick={onRetry}>
              Reintentar
            </Button>
          )}
          {onSaveAsDraft && (
            <Button variant="secondary" onClick={onSaveAsDraft}>
              Guardar borrador
            </Button>
          )}
        </>
      }
    />
  );
}

/** SUNAT returned a rejection (CDR with code 2xxx/4xxx). */
export function SunatRejectedState({
  code,
  description,
  onRetry,
}: {
  code?: string;
  description?: string;
  onRetry?: () => void;
}) {
  const detail = [code && `Código ${code}`, description].filter(Boolean).join(' — ');
  return (
    <EmptyState
      variant="danger"
      icon="x-circle"
      title="Comprobante rechazado por SUNAT"
      description={detail || 'SUNAT no aceptó el comprobante. Revisa los datos e intenta de nuevo.'}
      action={
        onRetry && (
          <Button variant="danger" icon="ph ph-arrow-clockwise" onClick={onRetry}>
            Corregir y reenviar
          </Button>
        )
      }
    />
  );
}

/** Generic network / fetch error (non-SUNAT). */
export function NetworkErrorState({ onRetry }: RetryProps) {
  return (
    <EmptyState
      variant="danger"
      icon="wifi-slash"
      title="Sin conexión"
      description="No se pudo completar la operación. Verifica tu conexión e intenta de nuevo."
      action={
        onRetry && (
          <Button variant="secondary" icon="ph ph-arrow-clockwise" onClick={onRetry}>
            Reintentar
          </Button>
        )
      }
    />
  );
}

/** No records match the current search / filter. */
export function NoResultsState({
  query,
  onClear,
}: {
  query?: string;
  onClear?: () => void;
}) {
  return (
    <EmptyState
      variant="default"
      icon="magnifying-glass"
      title="Sin resultados"
      description={
        query
          ? `No se encontraron coincidencias para "${query}". Revisa los filtros o el término de búsqueda.`
          : 'No hay registros que coincidan con los filtros actuales.'
      }
      action={
        onClear && (
          <Button variant="secondary" onClick={onClear}>
            Limpiar filtros
          </Button>
        )
      }
    />
  );
}

/** First-use / truly empty list — no records exist yet. */
export function EmptyListState({
  entity,
  action,
}: {
  /** Singular entity name, e.g. "venta" or "producto". */
  entity: string;
  /** Optional primary action button (e.g. a "New sale" button). */
  action?: ReactNode;
}) {
  return (
    <EmptyState
      variant="default"
      icon="tray"
      title={`Aún no hay ${entity}s`}
      description={`Crea tu primer${entity.match(/^[aeiouAEIOU]/) ? 'a' : ''} ${entity} para comenzar.`}
      action={action}
    />
  );
}
