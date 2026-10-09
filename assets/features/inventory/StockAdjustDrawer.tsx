import { useMemo, useState } from 'react';
import { AppDropdown, Button, Drawer, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { submitStockAdjust, type StockAdjustOperation } from './stockEntryApi';
import './InventoryHub.scss';

const REASON_OPTIONS = [
  { value: 'COUNT_CORRECTION', label: 'Corrección por conteo' },
  { value: 'INITIAL_STOCK', label: 'Carga inicial' },
  { value: 'LOSS', label: 'Pérdida / merma' },
  { value: 'DAMAGE', label: 'Producto dañado' },
  { value: 'THEFT', label: 'Robo / sustracción' },
  { value: 'EXPIRY', label: 'Producto vencido' },
  { value: 'OTHER', label: 'Otro' },
];

export interface StockAdjustTarget {
  productId: string;
  productIri: string;
  productLabel: string;
  unitLabel: string;
  warehouseId: string;
  warehouseIri: string;
  warehouseLabel: string;
  qtyOnHand: string;
}

function formatStockQty(value: number): string {
  return value.toLocaleString('es-PE', { maximumFractionDigits: 3, minimumFractionDigits: 0 });
}

interface StockAdjustDrawerProps {
  target: StockAdjustTarget | null;
  onClose: () => void;
  onApplied: () => void;
}

type UiOperation = 'receive' | 'issue' | 'count';

const OPERATION_MAP: Record<UiOperation, StockAdjustOperation> = {
  receive: 'RECEIPT',
  issue: 'ISSUE',
  count: 'ADJUSTMENT',
};

function StockAdjustForm({
  target,
  onClose,
  onApplied,
}: {
  target: StockAdjustTarget;
  onClose: () => void;
  onApplied: () => void;
}) {
  const [operation, setOperation] = useState<UiOperation>('receive');
  const [qty, setQty] = useState('');
  const [reason, setReason] = useState('COUNT_CORRECTION');
  const [reference, setReference] = useState('');
  const [note, setNote] = useState('');
  const [saving, setSaving] = useState(false);

  const qtyLabel = useMemo(() => {
    const unitSuffix = target.unitLabel ? ` (${target.unitLabel})` : '';
    if (operation === 'count') return `Stock real (conteo físico)${unitSuffix}`;
    if (operation === 'issue') return `Cantidad a salir${unitSuffix}`;
    return `Cantidad a ingresar${unitSuffix}`;
  }, [operation, target.unitLabel]);

  const currentStock = Number(target.qtyOnHand ?? 0);

  const handleApply = async () => {
    const parsedQty = Number(qty);
    if (!Number.isFinite(parsedQty) || parsedQty < 0) {
      toast('Ingresa una cantidad válida', 'error');
      return;
    }

    if (operation === 'issue' && parsedQty <= 0) {
      toast('La cantidad a salir debe ser mayor a cero', 'error');
      return;
    }

    if (operation === 'receive' && parsedQty <= 0) {
      toast('La cantidad a ingresar debe ser mayor a cero', 'error');
      return;
    }

    if (operation === 'count' && reason === 'OTHER' && !note.trim()) {
      toast('Agrega una nota cuando el motivo es "Otro"', 'error');
      return;
    }

    const apiQty = operation === 'count' ? String(parsedQty) : String(parsedQty);

    if (target.warehouseIri === '' || target.productIri === '') {
      toast('No se pudo identificar el producto o almacén de esta fila', 'error');
      return;
    }

    setSaving(true);
    try {
      await submitStockAdjust({
        type: OPERATION_MAP[operation],
        warehouseIri: target.warehouseIri,
        productIri: target.productIri,
        qty: apiQty,
        reason: operation === 'count' ? reason : undefined,
        note: note.trim() || undefined,
        reference: reference.trim() || undefined,
      });
      toast('Stock actualizado', 'success');
      onApplied();
      onClose();
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'No se pudo aplicar el ajuste de stock';
      toast(message, 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <form
      className="stock-adjust-drawer"
      data-testid="stock-adjust-drawer"
      onSubmit={(event) => {
        event.preventDefault();
        void handleApply();
      }}
    >
      <div className="stock-adjust-drawer__intro">
        <p className="stock-adjust-drawer__product">{target.productLabel}</p>
        <p className="stock-adjust-drawer__warehouse">
          {target.warehouseLabel} — {formatStockQty(currentStock)} {target.unitLabel} en stock
        </p>
      </div>

      <div className="stock-adjust-drawer__ops" role="group" aria-label="Tipo de operación">
        <button
          type="button"
          className={`stock-adjust-drawer__op stock-adjust-drawer__op--receive${operation === 'receive' ? ' stock-adjust-drawer__op--active' : ''}`}
          onClick={() => setOperation('receive')}
        >
          Ingreso
        </button>
        <button
          type="button"
          className={`stock-adjust-drawer__op${operation === 'issue' ? ' stock-adjust-drawer__op--active' : ''}`}
          onClick={() => setOperation('issue')}
        >
          Salida
        </button>
        <button
          type="button"
          className={`stock-adjust-drawer__op${operation === 'count' ? ' stock-adjust-drawer__op--active' : ''}`}
          onClick={() => setOperation('count')}
        >
          Conteo
        </button>
      </div>

      <div className="stock-adjust-drawer__section">
        <label className="stock-adjust-drawer__field">
          <span>{qtyLabel}</span>
          <TextField
            type="number"
            min={0}
            step="any"
            value={qty}
            onChange={(e) => setQty(e.target.value)}
          />
        </label>

        {operation === 'count' && (
          <label className="stock-adjust-drawer__field">
            <span>Motivo</span>
            <AppDropdown
              value={reason}
              onChange={setReason}
              options={REASON_OPTIONS}
              variant="form"
              showFieldLabel={false}
            />
          </label>
        )}

        <label className="stock-adjust-drawer__field">
          <span>Referencia</span>
          <TextField
            value={reference}
            onChange={(e) => setReference(e.target.value)}
            placeholder="Ej. OC-0043"
          />
        </label>

        <label className="stock-adjust-drawer__field">
          <span>Nota</span>
          <TextField
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Opcional"
          />
        </label>
      </div>

      <div className="stock-adjust-drawer__actions">
        <Button variant="ghost" type="button" onClick={onClose} disabled={saving}>
          Cancelar
        </Button>
        <Button variant="primary" type="submit" loading={saving}>
          Aplicar ajuste
        </Button>
      </div>
    </form>
  );
}

export const StockAdjustDrawer = ({ target, onClose, onApplied }: StockAdjustDrawerProps) => {
  const formKey = target ? `${target.productId}:${target.warehouseId}` : 'closed';

  return (
    <Drawer
      isOpen={target !== null}
      onClose={onClose}
      title="Ajustar stock"
      width={440}
      scrim="overlay"
      className="stock-adjust-drawer-panel"
    >
      {target && (
        <StockAdjustForm key={formKey} target={target} onClose={onClose} onApplied={onApplied} />
      )}
    </Drawer>
  );
};
