import { useMutation, useQueryClient } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { getIRI } from '@shared/lib/SharedUtils';
import {
  extractCollection,
  isCashPaymentMethod,
  nowUtcIso,
  todayBusinessDate,
} from '@shared/pos/helpers';
import { computeLineAmount } from '@shared/lib/saleCalculations';
import { buildSettlementPayload, createSinglePaymentLine } from '@shared/lib/salePayments';
import { useWarehouses } from '@shared/hooks/useWarehouses';
import { usePosPaymentMethods } from '@features/pos/usePosCatalogData';
import * as api from './restaurantApi';
import { getCheckoutIdempotencyKey } from './checkoutIdempotency';
import type { OrderItemMock } from '../types';

const API = getCoreApiBaseUrl();

interface CheckoutParams {
  orderId: string;
  items: OrderItemMock[];
}

/**
 * "Cobrar mesa": builds the exact payload `usePosCheckout.ts` sends to
 * `POST /api/sales` (the same endpoint the web POS already uses) and, once
 * the sale exists, closes the Restaurant order with the resulting sale id.
 * Restaurant's backend never talks to Sales directly — this is the whole
 * integration, done from the frontend like the POS already does it.
 */
export function useCheckoutOrder() {
  const queryClient = useQueryClient();
  const { warehouses } = useWarehouses();
  const { data: paymentMethods = [] } = usePosPaymentMethods();

  return useMutation({
    mutationFn: async ({ orderId, items }: CheckoutParams) => {
      const warehouse = warehouses[0];
      if (!warehouse) {
        throw new Error('No hay un almacén configurado para registrar la venta.');
      }

      const cashMethod = paymentMethods.find((m) => isCashPaymentMethod(m)) ?? paymentMethods[0];
      if (!cashMethod) {
        throw new Error('No hay métodos de pago configurados.');
      }

      const total = items.reduce((sum, item) => sum + Number(item.unitPrice) * item.qty, 0);
      const paymentLine = createSinglePaymentLine(cashMethod.id, total);
      const settlements = buildSettlementPayload([paymentLine], paymentMethods, total);

      const writeItems = items.map((item) => ({
        product: getIRI(`${API}products`, item.productId),
        qty: item.qty,
        unitPrice: item.unitPrice,
        description: item.productName,
        discountAmount: '0.00',
        netAmount: computeLineAmount(item.qty, item.unitPrice, 0).netAmount.toFixed(2),
      }));
      const idempotencyKey = await getCheckoutIdempotencyKey(orderId);

      const response = await httpClient.post<Record<string, unknown>>(
        `${API}sales`,
        {
          issuedAt: nowUtcIso(),
          issuedDate: todayBusinessDate(),
          warehouse: warehouse.iri,
          customer: null,
          paymentMethod: getIRI(`${API}payment-methods`, String(cashMethod.id)),
          settlements,
          note: 'Venta generada desde Restaurant (mesa).',
          discountPercent: '0.00',
          cashRoundingAdjustment: '0.00',
          emitInvoice: true,
          requestedVoucherType: 'AUTO',
          currency: 'PEN',
          exchangeRate: '1.000000',
          grandTotal: total.toFixed(2),
          writeItems,
        },
        { headers: { 'Idempotency-Key': idempotencyKey } },
      );

      const saleId = String(response.data.id ?? extractCollection([response.data])[0]);
      await api.closeOrder(orderId, saleId);

      return saleId;
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['restaurant', 'tables'] });
      void queryClient.invalidateQueries({ queryKey: ['restaurant', 'orders'] });
    },
  });
}
