import { useEffect, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Button, Card, FormField, FormLayout, TextField } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import toast from '@shared/lib/SharedUtils';
import { purchaseOrderEndpoints, withPurchasingBranch } from './api/purchaseOrderEndpoints';

const purchaseEndpoints = createEndpoints('purchases');

interface PurchaseOrderItemDto {
  id: number;
  product: { id: string; name: string } | null;
  nameSnapshot: string;
  orderedQty: string;
  receivedQty: string;
  pendingQty: string;
  expectedUnitCost: string;
}

interface PurchaseOrderDto {
  id: string;
  supplier: { id: string; businessName: string } | null;
  branch: { id: string } | null;
  warehouse: { id: string } | null;
  currency: string;
  items: PurchaseOrderItemDto[];
}

interface ReceiveLine {
  purchaseOrderItemId: number;
  productId: string | null;
  label: string;
  qty: string;
  unitCost: string;
  pendingQty: string;
}

export const ReceiveAgainstPoPage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [search] = useSearchParams();
  const branchId = search.get('branchId') ?? '';
  const ordersUrl = withPurchasingBranch('/purchase-orders', branchId);
  const [loadError, setLoadError] = useState(false);
  const [order, setOrder] = useState<PurchaseOrderDto | null>(null);
  const [lines, setLines] = useState<ReceiveLine[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [docType, setDocType] = useState('01');
  const [docSeries, setDocSeries] = useState('');
  const [docNumber, setDocNumber] = useState('');

  useEffect(() => {
    if (!id) return;
    const controller = new AbortController();
    httpClient
      .get<PurchaseOrderDto>(withPurchasingBranch(purchaseOrderEndpoints.detail(id), branchId), {
        headers: { Accept: 'application/ld+json' },
        signal: controller.signal,
      })
      .then((response) => {
        if (controller.signal.aborted) return;
        setLoadError(false);
        const po = response.data;
        setOrder(po);
        setLines(
          po.items
            .filter((item) => Number(item.pendingQty) > 0)
            .map((item) => ({
              purchaseOrderItemId: item.id,
              productId: item.product?.id ?? null,
              label: item.nameSnapshot,
              qty: item.pendingQty,
              unitCost: item.expectedUnitCost,
              pendingQty: item.pendingQty,
            })),
        );
      })
      .catch(() => {
        if (!controller.signal.aborted) setLoadError(true);
      });
    return () => controller.abort();
  }, [id, branchId]);

  const updateLine = (index: number, patch: Partial<ReceiveLine>) => {
    setLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)));
  };

  const handleSubmit = () => {
    if (!order || !id || isSubmitting || !docType.trim() || !docSeries.trim() || !docNumber.trim())
      return;

    const items = lines
      .filter((line) => Number(line.qty) > 0)
      .map((line) => ({
        product: line.productId ? `/api/products/${line.productId}` : null,
        qty: line.qty,
        unitCost: line.unitCost,
        grossAmount: (Number(line.qty) * Number(line.unitCost)).toFixed(2),
        totalAmount: (Number(line.qty) * Number(line.unitCost)).toFixed(2),
        purchaseOrderItemId: line.purchaseOrderItemId,
      }));

    if (items.length === 0) {
      toast('Indica al menos una cantidad a recibir', 'error');
      return;
    }

    setIsSubmitting(true);
    httpClient
      .post(withPurchasingBranch(purchaseEndpoints.list(), branchId), {
        supplier: order.supplier ? `/api/suppliers/${order.supplier.id}` : null,
        branch: order.branch ? `/api/branches/${order.branch.id}` : null,
        warehouse: order.warehouse ? `/api/warehouses/${order.warehouse.id}` : null,
        currency: order.currency,
        purchaseOrder: purchaseOrderEndpoints.detail(order.id),
        supplierDocType: docType.trim(),
        supplierDocSeries: docSeries.trim().toUpperCase(),
        supplierDocNumber: docNumber.trim(),
        items,
      })
      .then(() => {
        toast('Mercadería recibida', 'success');
        navigate(ordersUrl);
      })
      .catch((err: unknown) => {
        const message = err instanceof Error ? err.message : 'No se pudo registrar la recepción.';
        toast(message, 'error');
      })
      .finally(() => setIsSubmitting(false));
  };

  if (loadError) {
    return <Card title="Recibir mercadería">No se pudo cargar la orden de compra.</Card>;
  }

  if (!order) {
    return <Card title="Recibir mercadería">Cargando…</Card>;
  }

  return (
    <Card title={`Recibir mercadería — OC ${order.id}`}>
      {lines.length === 0 && <p>Esta orden de compra no tiene ítems pendientes.</p>}
      {lines.map((line, index) => (
        <div
          key={line.purchaseOrderItemId}
          style={{ display: 'flex', gap: 12, alignItems: 'center', marginBottom: 8 }}
        >
          <span style={{ flex: 2 }}>{line.label}</span>
          <span style={{ flex: 1, color: 'var(--text-muted)' }}>Pendiente: {line.pendingQty}</span>
          <input
            type="number"
            min={0}
            max={Number(line.pendingQty)}
            step={0.001}
            value={line.qty}
            onChange={(e) => updateLine(index, { qty: e.target.value })}
            style={{ flex: 1 }}
          />
          <input
            type="number"
            min={0}
            step={0.0001}
            value={line.unitCost}
            onChange={(e) => updateLine(index, { unitCost: e.target.value })}
            style={{ flex: 1 }}
          />
        </div>
      ))}
      <FormLayout
        onSubmit={(event) => {
          event.preventDefault();
          handleSubmit();
        }}
      >
        <FormField label="Tipo de comprobante del proveedor" htmlFor="receive-doc-type" required>
          <TextField
            id="receive-doc-type"
            value={docType}
            onChange={(event) => setDocType(event.target.value)}
            required
          />
        </FormField>
        <FormField label="Serie" htmlFor="receive-doc-series" required>
          <TextField
            id="receive-doc-series"
            value={docSeries}
            onChange={(event) => setDocSeries(event.target.value)}
            required
          />
        </FormField>
        <FormField label="Número de comprobante" htmlFor="receive-doc-number" required>
          <TextField
            id="receive-doc-number"
            value={docNumber}
            onChange={(event) => setDocNumber(event.target.value)}
            required
          />
        </FormField>
        <Button
          variant="primary"
          type="submit"
          fullWidth
          loading={isSubmitting}
          disabled={lines.length === 0}
          data-testid="register-purchase-receipt"
        >
          Registrar recepción
        </Button>
      </FormLayout>
    </Card>
  );
};
