import { useState } from 'react';
import { AppDialog, Button, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { productIriFromValue, submitStockAdjust, warehouseIriFromValue } from './stockEntryApi';
import './InventoryHub.scss';

export interface BulkStockReceiptTarget {
  stockItemId: string;
  productId: string;
  warehouseId: string;
  productLabel: string;
}

interface BulkStockReceiptDialogProps {
  targets: BulkStockReceiptTarget[];
  open: boolean;
  onClose: () => void;
  onApplied: () => void;
}

export const BulkStockReceiptDialog = ({
  targets,
  open,
  onClose,
  onApplied,
}: BulkStockReceiptDialogProps) => {
  const [qty, setQty] = useState('1');
  const [reference, setReference] = useState('');
  const [saving, setSaving] = useState(false);

  const handleApply = async () => {
    const parsedQty = Number(qty);
    if (!Number.isFinite(parsedQty) || parsedQty <= 0) {
      toast('Ingresa una cantidad válida mayor a cero', 'error');
      return;
    }

    setSaving(true);
    try {
      for (const target of targets) {
        const warehouseIri = warehouseIriFromValue(target.warehouseId);
        const productIri = productIriFromValue(target.productId);
        await submitStockAdjust({
          type: 'RECEIPT',
          warehouseIri,
          productIri,
          qty: String(parsedQty),
          reference: reference.trim() || undefined,
        });
      }
      toast(`Ingreso registrado para ${targets.length} producto(s)`, 'success');
      onApplied();
      onClose();
    } catch {
      toast('No se pudo completar el ingreso masivo', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppDialog
      open={open}
      onClose={onClose}
      title="Ingreso masivo"
      width={440}
    >
      <div className="bulk-stock-receipt" data-testid="bulk-stock-receipt-dialog">
        <p className="bulk-stock-receipt__intro">
          Se registrará la misma cantidad como ingreso manual para{' '}
          <strong>{targets.length}</strong> producto(s) seleccionado(s).
        </p>

        <label className="stock-adjust-drawer__field">
          <span>Cantidad por producto</span>
          <TextField
            type="number"
            min={0}
            step="any"
            value={qty}
            onChange={(e) => setQty(e.target.value)}
          />
        </label>

        <label className="stock-adjust-drawer__field">
          <span>Referencia</span>
          <TextField
            value={reference}
            onChange={(e) => setReference(e.target.value)}
            placeholder="Ej. OC-0043"
          />
        </label>

        <ul className="bulk-stock-receipt__list">
          {targets.slice(0, 5).map((target) => (
            <li key={target.stockItemId}>{target.productLabel}</li>
          ))}
          {targets.length > 5 && (
            <li className="bulk-stock-receipt__more">+ {targets.length - 5} más…</li>
          )}
        </ul>

        <div className="stock-adjust-drawer__actions">
          <Button variant="ghost" onClick={onClose} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="primary" onClick={() => void handleApply()} loading={saving}>
            Aplicar ingreso
          </Button>
        </div>
      </div>
    </AppDialog>
  );
};

export function parseStockItemCompositeId(stockItemId: string): { warehouseId: string; productId: string } | null {
  const separator = stockItemId.indexOf(':');
  if (separator <= 0) return null;
  const warehouseId = stockItemId.slice(0, separator);
  const productId = stockItemId.slice(separator + 1);
  if (!warehouseId || !productId) return null;
  return { warehouseId, productId };
}