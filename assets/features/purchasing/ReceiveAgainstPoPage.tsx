import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Button, Card } from '@nubitio/ui';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import toast from '@shared/lib/SharedUtils';
import { purchaseOrderEndpoints } from './api/purchaseOrderEndpoints';

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
  const [order, setOrder] = useState<PurchaseOrderDto | null>(null);
  const [lines, setLines] = useState<ReceiveLine[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!id) return;
    httpClient
      .get<PurchaseOrderDto>(purchaseOrderEndpoints.detail(id), {
        headers: { Accept: 'application/ld+json' },
      })
      .then((response) => {
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
      });
  }, [id]);

  const updateLine = (index: number, patch: Partial<ReceiveLine>) => {
    setLines((prev) => prev.map((line, i) => (i === index ? { ...line, ...patch } : line)));
  };

  const handleSubmit = () => {
    if (!order || !id) return;

    const items = lines
      .filter((line) => Number(line.qty) > 0)
      .map((line) => ({
        product: line.productId ? `/api/products/${line.productId}` : null,
        qty: line.qty,
        unitCost: line.unitCost,
        grossAmount: (Number(line.qty) * Number(line.unitCost)).toFixed(2),
        totalAmount: (Number(line.qty) * Number(line.unitCost)).toFixed(2),
        purchaseOrderItem: `/api/purchase-order-items/${line.purchaseOrderItemId}`,
      }));

    if (items.length === 0) {
      toast('Indica al menos una cantidad a recibir', 'error');
      return;
    }

    setIsSubmitting(true);
    httpClient
      .post(purchaseEndpoints.list(), {
        supplier: order.supplier ? `/api/suppliers/${order.supplier.id}` : null,
        branch: order.branch ? `/api/branches/${order.branch.id}` : null,
        warehouse: order.warehouse ? `/api/warehouses/${order.warehouse.id}` : null,
        currency: order.currency,
        purchaseOrder: purchaseOrderEndpoints.detail(order.id),
        supplierDocType: '01',
        supplierDocSeries: 'E001',
        supplierDocNumber: '1',
        items,
      })
      .then(() => {
        toast('Mercadería recibida', 'success');
        navigate('/purchase-orders');
      })
      .catch((err: unknown) => {
        const message = err instanceof Error ? err.message : 'No se pudo registrar la recepción.';
        toast(message, 'error');
      })
      .finally(() => setIsSubmitting(false));
  };

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
      <Button variant="success" fullWidth loading={isSubmitting} onClick={handleSubmit}>
        Registrar recepción
      </Button>
    </Card>
  );
};
