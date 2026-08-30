/* eslint-disable react-hooks/set-state-in-effect -- cycle-count lifecycle synchronizes scan and count state */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AppDropdown, Button, TextField } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import toast from '@shared/lib/SharedUtils';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { useSessionInfo } from '@shared/sessionInfo';
import { useWarehouses } from '@shared/hooks/useWarehouses';
import { productEndpoints } from '@features/catalog/api/catalogEndpoints';
import { submitCycleCount } from './stockEntryApi';
import './InventoryHub.scss';

const API = getCoreApiBaseUrl();

interface CountLine {
  key: string;
  productId: string;
  productIri: string;
  productLabel: string;
  systemQty: string;
  countedQty: string;
}

interface StockItemApiRow {
  id?: string;
  product?: {
    id?: string;
    code?: string | null;
    name?: string;
    '@id'?: string;
  } | string;
  qtyOnHand?: string;
}

interface ProductOption {
  value: string;
  label: string;
}

function extractHydraItems(raw: unknown): unknown[] {
  if (Array.isArray(raw)) return raw;
  if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>;
    if (Array.isArray(obj['hydra:member'])) return obj['hydra:member'] as unknown[];
    if (Array.isArray(obj['items'])) return obj['items'] as unknown[];
    if (Array.isArray(obj['data'])) return obj['data'] as unknown[];
  }
  return [];
}

function formatProductLabel(product: StockItemApiRow['product']): string {
  if (!product || typeof product !== 'object') return String(product ?? '');
  const row = product as { code?: string | null; name?: string };
  return [row.code, row.name].filter(Boolean).join(' - ');
}

function productIri(product: StockItemApiRow['product'], productId: string): string {
  if (product && typeof product === 'object') {
    const iri = (product as { '@id'?: string })['@id'];
    if (iri) return iri;
  }
  return `${API}products/${productId}`;
}

function parseQty(value: string): number {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : 0;
}

function formatQtyInput(value: string): string {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return '0';
  return String(numeric);
}

function buildLineKey(productId: string): string {
  return productId;
}

async function fetchStockItems(warehouseId: string): Promise<CountLine[]> {
  const response = await httpClient.get<unknown>(`${API}stock-items?pagination=false`);
  const rows = extractHydraItems(response.data) as StockItemApiRow[];

  return rows
    .filter((row) => String(row.id ?? '').startsWith(`${warehouseId}:`))
    .map((row) => {
      const product = row.product;
      const productId =
        product && typeof product === 'object'
          ? String((product as { id?: string }).id ?? '')
          : String(product ?? '').split('/').pop() ?? '';

      if (!productId) return null;

      const systemQty = formatQtyInput(String(row.qtyOnHand ?? '0'));
      return {
        key: buildLineKey(productId),
        productId,
        productIri: productIri(product, productId),
        productLabel: formatProductLabel(product),
        systemQty,
        countedQty: systemQty,
      } satisfies CountLine;
    })
    .filter((line): line is CountLine => line !== null)
    .sort((a, b) => a.productLabel.localeCompare(b.productLabel, 'es'));
}

async function fetchProductOptions(): Promise<ProductOption[]> {
  const response = await httpClient.get<unknown>(`${productEndpoints.list()}?pagination=false`);
  const rows = extractHydraItems(response.data) as Array<Record<string, unknown>>;

  return rows
    .map((row) => ({
      value: String(row['id'] ?? ''),
      label: [row['code'], row['name']].filter(Boolean).join(' - '),
    }))
    .filter((option) => option.value && option.label)
    .sort((a, b) => a.label.localeCompare(b.label, 'es'));
}

export const CycleCountPage = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { currentBranchId } = useSessionInfo();
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const { warehouses } = useWarehouses({ branchId: currentBranchId });

  const prefillWarehouseId = searchParams.get('warehouse') ?? '';
  const prefillProductIds = useMemo(
    () =>
      (searchParams.get('products') ?? '')
        .split(',')
        .map((id) => id.trim())
        .filter(Boolean),
    [searchParams],
  );
  const returnTo = searchParams.get('returnTo') ?? '/inventory/stock';

  const defaultWarehouseId =
    prefillWarehouseId ||
    runtimeConfig?.defaults.warehouse?.id ||
    warehouses[0]?.id ||
    '';

  const showWarehouseSelector = runtimeConfig?.ui.inventory.showWarehouseSelector ?? true;

  const [warehouseId, setWarehouseId] = useState(defaultWarehouseId);
  const [lines, setLines] = useState<CountLine[]>([]);
  const [reference, setReference] = useState('');
  const [note, setNote] = useState('');
  const [addProductId, setAddProductId] = useState('');
  const [saving, setSaving] = useState(false);

  const { data: productOptions = [], isLoading: productsLoading } = useQuery({
    queryKey: ['cycle-count-products'],
    queryFn: fetchProductOptions,
    staleTime: 5 * 60_000,
  });

  const {
    data: stockLines = [],
    isLoading: stockLoading,
    isFetching: stockFetching,
  } = useQuery({
    queryKey: ['cycle-count-stock-items', warehouseId],
    queryFn: () => fetchStockItems(warehouseId),
    enabled: Boolean(warehouseId),
  });

  useEffect(() => {
    if (!warehouseId && defaultWarehouseId) {
      setWarehouseId(defaultWarehouseId);
    }
  }, [defaultWarehouseId, warehouseId]);

  useEffect(() => {
    if (stockLoading || stockFetching) return;

    const filtered =
      prefillProductIds.length > 0
        ? stockLines.filter((line) => prefillProductIds.includes(line.productId))
        : stockLines;

    setLines(filtered);
  }, [stockLines, stockLoading, stockFetching, prefillProductIds]);

  const warehouseOptions = useMemo(
    () => warehouses.map((warehouse) => ({ value: warehouse.id, label: warehouse.name })),
    [warehouses],
  );

  const varianceCount = useMemo(
    () => lines.filter((line) => parseQty(line.countedQty) !== parseQty(line.systemQty)).length,
    [lines],
  );

  const updateLineQty = useCallback((key: string, countedQty: string) => {
    setLines((current) =>
      current.map((line) => (line.key === key ? { ...line, countedQty } : line)),
    );
  }, []);

  const removeLine = useCallback((key: string) => {
    setLines((current) => current.filter((line) => line.key !== key));
  }, []);

  const addProductLine = useCallback(() => {
    if (!addProductId) return;

    const option = productOptions.find((item) => item.value === addProductId);
    const key = buildLineKey(addProductId);
    if (lines.some((line) => line.key === key)) {
      toast('El producto ya está en la lista de conteo', 'warning');
      return;
    }

    const existing = stockLines.find((line) => line.productId === addProductId);
    const systemQty = existing?.systemQty ?? '0';

    setLines((current) => [
      ...current,
      {
        key,
        productId: addProductId,
        productIri: `${API}products/${addProductId}`,
        productLabel: option?.label ?? addProductId,
        systemQty,
        countedQty: systemQty,
      },
    ]);
    setAddProductId('');
  }, [addProductId, lines, productOptions, stockLines]);

  const handleApply = async () => {
    if (!warehouseId) {
      toast('Selecciona un almacén', 'error');
      return;
    }

    const changedLines = lines.filter(
      (line) => parseQty(line.countedQty) !== parseQty(line.systemQty),
    );

    if (changedLines.length === 0) {
      toast('No hay diferencias entre el conteo y el stock en sistema', 'warning');
      return;
    }

    const invalidLine = changedLines.find((line) => parseQty(line.countedQty) < 0);
    if (invalidLine) {
      toast('Las cantidades contadas no pueden ser negativas', 'error');
      return;
    }

    setSaving(true);
    try {
      await submitCycleCount({
        warehouseIri: `${API}warehouses/${warehouseId}`,
        items: changedLines.map((line) => ({
          productIri: line.productIri,
          qty: formatQtyInput(line.countedQty),
        })),
        note: note.trim() || undefined,
        reference: reference.trim() || undefined,
      });
      toast(`Conteo aplicado (${changedLines.length} producto(s))`, 'success');
      navigate(returnTo);
    } catch {
      toast('No se pudo aplicar el conteo cíclico', 'error');
    } finally {
      setSaving(false);
    }
  };

  const availableProductOptions = useMemo(
    () => productOptions.filter((option) => !lines.some((line) => line.productId === option.value)),
    [lines, productOptions],
  );

  return (
    <div className="cycle-count-page" data-testid="cycle-count-page">
      <header className="cycle-count-page__header">
        <div>
          <h3 className="cycle-count-page__title">Conteo cíclico</h3>
          <p className="cycle-count-page__subtitle">
            Registra cantidades físicas y aplica correcciones en un solo documento de ajuste.
          </p>
        </div>
        <div className="cycle-count-page__header-actions">
          <Button variant="ghost" onClick={() => navigate(returnTo)} disabled={saving}>
            Cancelar
          </Button>
          <Button
            variant="primary"
            onClick={() => void handleApply()}
            loading={saving}
            disabled={varianceCount === 0}
            data-testid="cycle-count-apply"
          >
            Aplicar conteo ({varianceCount})
          </Button>
        </div>
      </header>

      <div className="cycle-count-page__stats" data-testid="cycle-count-stats">
        <div className="cycle-count-page__stat">
          <span className="cycle-count-page__stat-label">Productos</span>
          <span className="cycle-count-page__stat-value">{lines.length}</span>
        </div>
        <div className="cycle-count-page__stat cycle-count-page__stat--variance">
          <span className="cycle-count-page__stat-label">Con diferencia</span>
          <span className="cycle-count-page__stat-value">{varianceCount}</span>
        </div>
      </div>

      <div className="cycle-count-page__meta">
        {showWarehouseSelector ? (
          <label className="cycle-count-page__field">
            <span>Almacén</span>
            <AppDropdown
              value={warehouseId}
              onChange={setWarehouseId}
              options={warehouseOptions}
              variant="form"
              showFieldLabel={false}
              disabled={saving || warehouseOptions.length === 0}
            />
          </label>
        ) : (
          <p className="cycle-count-page__warehouse-label">
            Almacén: {warehouses.find((warehouse) => warehouse.id === warehouseId)?.name ?? '—'}
          </p>
        )}

        <label className="cycle-count-page__field">
          <span>Referencia</span>
          <TextField
            value={reference}
            onChange={(e) => setReference(e.target.value)}
            placeholder="Ej. Conteo zona A — Estante 3"
            disabled={saving}
          />
        </label>

        <label className="cycle-count-page__field">
          <span>Nota</span>
          <TextField
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Opcional"
            disabled={saving}
          />
        </label>
      </div>

      <div className="cycle-count-page__add-row">
        <AppDropdown
          value={addProductId}
          onChange={setAddProductId}
          options={availableProductOptions}
          variant="form"
          showFieldLabel={false}
          placeholder="Agregar producto al conteo…"
          disabled={saving || productsLoading || availableProductOptions.length === 0}
        />
        <Button
          variant="secondary"
          onClick={addProductLine}
          disabled={!addProductId || saving}
          data-testid="cycle-count-add-product"
        >
          Agregar
        </Button>
      </div>

      <div className="cycle-count-page__table-wrap">
        <table className="cycle-count-page__table" data-testid="cycle-count-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th className="cycle-count-page__num">En sistema</th>
              <th className="cycle-count-page__num">Contado</th>
              <th className="cycle-count-page__num">Diferencia</th>
              <th aria-label="Acciones" />
            </tr>
          </thead>
          <tbody>
            {stockLoading && lines.length === 0 ? (
              <tr>
                <td colSpan={5} className="cycle-count-page__empty">
                  Cargando existencias…
                </td>
              </tr>
            ) : lines.length === 0 ? (
              <tr>
                <td colSpan={5} className="cycle-count-page__empty">
                  No hay productos en este almacén. Agrega productos manualmente para contar.
                </td>
              </tr>
            ) : (
              lines.map((line) => {
                const variance = parseQty(line.countedQty) - parseQty(line.systemQty);
                const varianceClass =
                  variance === 0
                    ? 'cycle-count-page__variance--ok'
                    : variance > 0
                      ? 'cycle-count-page__variance--up'
                      : 'cycle-count-page__variance--down';

                return (
                  <tr key={line.key} data-testid="cycle-count-row">
                    <td>{line.productLabel}</td>
                    <td className="cycle-count-page__num">{line.systemQty}</td>
                    <td className="cycle-count-page__num">
                      <TextField
                        type="number"
                        min={0}
                        step="any"
                        value={line.countedQty}
                        onChange={(e) => updateLineQty(line.key, e.target.value)}
                        disabled={saving}
                        data-testid="cycle-count-qty-input"
                      />
                    </td>
                    <td className={`cycle-count-page__num ${varianceClass}`}>
                      {variance > 0 ? `+${variance}` : variance}
                    </td>
                    <td className="cycle-count-page__actions">
                      <button
                        type="button"
                        className="cycle-count-page__remove"
                        onClick={() => removeLine(line.key)}
                        disabled={saving}
                        aria-label={`Quitar ${line.productLabel}`}
                      >
                        <i className="ph ph-x" aria-hidden="true" />
                      </button>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
};
