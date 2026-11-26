import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { AppDropdown, Button, Combobox, Drawer, FormField, TextField } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { hasPermission } from '@shared/permissions';
import {
  listTransferDestinations,
  submitStockAdjust,
  type StockAdjustOperation,
} from './stockEntryApi';
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

type UiOperation = 'receive' | 'issue' | 'count' | 'transfer';

const OPERATION_MAP: Record<UiOperation, StockAdjustOperation> = {
  receive: 'RECEIPT',
  issue: 'ISSUE',
  count: 'ADJUSTMENT',
  transfer: 'TRANSFER',
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
  const canTransfer = hasPermission('inventory.transfer');
  const canAdjust = hasPermission('inventory.adjust');
  const canCount = hasPermission('inventory.cycle_count');
  const [operation, setOperation] = useState<UiOperation>(
    canAdjust ? 'receive' : canTransfer ? 'transfer' : 'count',
  );
  const [destinationId, setDestinationId] = useState<string | null>(null);
  const destinations = useQuery({
    queryKey: ['inventory', 'transfer-destinations', target.warehouseId],
    queryFn: listTransferDestinations,
    enabled: operation === 'transfer' && canTransfer,
    retry: false,
  });
  const [qty, setQty] = useState('');
  const [reason, setReason] = useState(canCount ? 'COUNT_CORRECTION' : 'INITIAL_STOCK');
  const reasonOptions = REASON_OPTIONS.filter((option) =>
    option.value === 'COUNT_CORRECTION' ? canCount : canAdjust,
  );
  const canSubmit =
    operation === 'transfer'
      ? canTransfer && Boolean(destinationId)
      : operation === 'count'
        ? reason === 'COUNT_CORRECTION'
          ? canCount
          : canAdjust
        : canAdjust;
  const [reference, setReference] = useState('');
  const [note, setNote] = useState('');
  const [saving, setSaving] = useState(false);

  const qtyLabel = useMemo(() => {
    const unitSuffix = target.unitLabel ? ` (${target.unitLabel})` : '';
    if (operation === 'count') return `Stock real (conteo físico)${unitSuffix}`;
    if (operation === 'transfer') return `Cantidad a trasladar${unitSuffix}`;
    if (operation === 'issue') return `Cantidad a salir${unitSuffix}`;
    return `Cantidad a ingresar${unitSuffix}`;
  }, [operation, target.unitLabel]);

  const currentStock = Number(target.qtyOnHand ?? 0);

  const handleApply = async () => {
    if (!canSubmit || saving) return;

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

    if (operation === 'transfer' && (parsedQty <= 0 || !destinationId)) {
      toast('Ingresa una cantidad positiva y selecciona un almacén destino.', 'error');
      return;
    }

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
        qty: String(parsedQty),
        targetWarehouseId: operation === 'transfer' ? (destinationId ?? undefined) : undefined,
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
        {(
          [
            ['receive', 'Ingreso', canAdjust],
            ['issue', 'Salida', canAdjust],
            ['count', 'Conteo', canCount || canAdjust],
            ['transfer', 'Traslado', canTransfer],
          ] as const
        )
          .filter(([, , allowed]) => allowed)
          .map(([value, label]) => (
            <Button
              key={value}
              type="button"
              size="sm"
              variant={operation === value ? 'secondary' : 'ghost'}
              aria-pressed={operation === value}
              disabled={saving}
              onClick={() => setOperation(value)}
            >
              {label}
            </Button>
          ))}
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

        {operation === 'transfer' && (
          <FormField
            label="Almacén destino"
            required
            error={
              destinations.isError ? 'No se pudieron cargar los almacenes autorizados.' : undefined
            }
          >
            <Combobox
              aria-label="Almacén destino"
              required
              value={destinationId}
              onChange={setDestinationId}
              disabled={saving || destinations.isPending}
              options={(destinations.data ?? [])
                .filter((warehouse) => warehouse.id !== target.warehouseId)
                .map((warehouse) => ({
                  value: warehouse.id,
                  label: `${warehouse.branchName} · ${warehouse.name}`,
                }))}
            />
          </FormField>
        )}

        {operation === 'count' && (
          <label className="stock-adjust-drawer__field">
            <span>Motivo</span>
            <AppDropdown
              value={reason}
              onChange={setReason}
              options={reasonOptions}
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
        <Button variant="primary" type="submit" loading={saving} disabled={!canSubmit}>
          {operation === 'transfer' ? 'Registrar traslado' : 'Aplicar ajuste'}
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
