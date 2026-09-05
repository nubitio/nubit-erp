/* eslint-disable react-hooks/set-state-in-effect -- sale totals intentionally rebalance payment drafts */
import React, { useEffect, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import type { FormHandle } from '@nubitio/crud';
import {
  buildSettlementPayload,
  creditPaymentAmount,
  createSinglePaymentLine,
  hasCreditPayment,
  rebalancePaymentLines,
  type SalePaymentLineDraft,
} from '@shared/lib/salePayments';
import { SplitPaymentsField } from '@shared/ui/split-payments/SplitPaymentsField';
import { extractCollection, isCreditPaymentMethod } from '@shared/pos/helpers';
import type { PaymentMethodOption } from '@shared/pos/types';

const API = getCoreApiBaseUrl();

interface SalePaymentsFieldProps {
  form: FormHandle | null;
  total: number;
  currency?: string;
}

export function SalePaymentsField({ form, total, currency = 'PEN' }: SalePaymentsFieldProps) {
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethodOption[]>([]);
  const [paymentLines, setPaymentLines] = useState<SalePaymentLineDraft[]>([]);

  useEffect(() => {
    let cancelled = false;

    httpRequestData<PaymentMethodOption[]>(
      httpClient.get<PaymentMethodOption[]>(`${API}payment-methods?itemsPerPage=100`),
    )
      .then((methods) => {
        if (cancelled) return;
        // Same rule as POS (usePosPaymentMethods): only offer active methods.
        setPaymentMethods(
          extractCollection<PaymentMethodOption>(methods)
            .map((method) => ({ ...method, id: Number(method.id) }))
            .filter((method) => method.isActive !== false && Number.isFinite(method.id)),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setPaymentMethods([]);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (paymentMethods.length === 0 || total <= 0) {
      return;
    }

    setPaymentLines((previousLines) => {
      if (previousLines.length === 0) {
        // Editing an existing sale: rebuild lines from the persisted payments.
        const existingPayments = form?.getFieldValue('payments');
        if (Array.isArray(existingPayments) && existingPayments.length > 0) {
          const lines = (existingPayments as { payment_method_id?: number; amount?: string }[])
            .filter((payment) => payment.payment_method_id != null)
            .map((payment) => ({
              methodId: Number(payment.payment_method_id),
              amount: String(payment.amount ?? '0'),
            }));

          const outstanding = Number(form?.getFieldValue('balanceDue') ?? 0);
          const creditMethod = paymentMethods.find(isCreditPaymentMethod);
          if (outstanding > 0 && creditMethod) {
            lines.push({ methodId: creditMethod.id, amount: outstanding.toFixed(2) });
          }

          if (lines.length > 0) {
            return rebalancePaymentLines(lines, total);
          }
        }

        const currentMethod = form?.getFieldValue('paymentMethod');
        const currentMethodId =
          typeof currentMethod === 'object' && currentMethod !== null && 'id' in currentMethod
            ? Number((currentMethod as { id?: number }).id)
            : Number(currentMethod ?? 0) || null;
        const outstanding = Math.max(
          0,
          Math.min(total, Number(form?.getFieldValue('balanceDue') ?? 0)),
        );
        const creditMethod = paymentMethods.find(isCreditPaymentMethod);

        // The read model stores only money that was actually collected in
        // `payments`. Reconstruct the receivable allocation from balanceDue.
        if (outstanding > 0 && creditMethod) {
          const collected = Math.max(0, total - outstanding);
          const lines: SalePaymentLineDraft[] = [];
          if (collected > 0 && currentMethodId != null) {
            lines.push({ methodId: currentMethodId, amount: collected.toFixed(2) });
          }
          lines.push({ methodId: creditMethod.id, amount: outstanding.toFixed(2) });

          return rebalancePaymentLines(lines, total);
        }

        const methodId = currentMethodId ?? paymentMethods[0]?.id;

        return [createSinglePaymentLine(methodId ?? null, total)];
      }

      if (previousLines.length === 1) {
        return [createSinglePaymentLine(previousLines[0].methodId, total)];
      }

      return rebalancePaymentLines(previousLines, total);
    });
  }, [form, paymentMethods, total]);

  useEffect(() => {
    if (paymentLines.length === 0 || total <= 0) {
      return;
    }

    const settlements = buildSettlementPayload(paymentLines, paymentMethods, total);
    form?.setFieldValue('settlements', settlements);

    const primaryMethodId = paymentLines[0]?.methodId;
    if (primaryMethodId != null) {
      form?.setFieldValue('paymentMethod', primaryMethodId);
    }
  }, [form, paymentLines, paymentMethods, total]);

  const creditSale = hasCreditPayment(paymentLines, paymentMethods);
  const creditAmount = creditPaymentAmount(paymentLines, paymentMethods);
  const customerDocument = String(form?.getFieldValue('customerDocument') ?? '').trim();
  const customerIsIdentified = customerDocument !== '' && customerDocument !== '00000000';

  return (
    <>
      <SplitPaymentsField
        paymentMethods={paymentMethods}
        total={total}
        currency={currency}
        value={paymentLines}
        onChange={setPaymentLines}
        variant="admin"
        layout="drawer"
      />
      {creditSale && (
        <div
          className={`sale-credit-notice${customerIsIdentified ? '' : ' is-warning'}`}
          role="status"
          data-testid="sales-credit-notice"
        >
          <i className={`ph ${customerIsIdentified ? 'ph-receipt' : 'ph-warning-circle'}`} />
          <span>
            {customerIsIdentified
              ? `${creditAmount.toLocaleString('es-PE', { style: 'currency', currency })} se registrará en Cuentas por cobrar.`
              : 'Selecciona un cliente identificado para registrar la cuenta por cobrar.'}
          </span>
        </div>
      )}
    </>
  );
}
