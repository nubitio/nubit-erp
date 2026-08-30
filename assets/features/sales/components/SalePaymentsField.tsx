/* eslint-disable react-hooks/set-state-in-effect -- sale totals intentionally rebalance payment drafts */
import React, { useEffect, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import type { FormHandle } from '@nubitio/crud';
import {
  buildPaymentPayload,
  createSinglePaymentLine,
  rebalancePaymentLines,
  type SalePaymentLineDraft,
} from '@shared/lib/salePayments';
import { SplitPaymentsField } from '@shared/ui/split-payments/SplitPaymentsField';
import { extractCollection } from '@shared/pos/helpers';
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
  const creditSale = Boolean(form?.getFieldValue('creditSale'));

  useEffect(() => {
    let cancelled = false;

    httpRequestData<PaymentMethodOption[]>(
      httpClient.get<PaymentMethodOption[]>(`${API}payment-methods?itemsPerPage=100`),
    )
      .then((methods) => {
        if (cancelled) return;
        // Same rule as POS (usePosPaymentMethods): only offer active methods.
        setPaymentMethods(
          extractCollection<PaymentMethodOption>(methods).filter(
            (method) => method.isActive !== false,
          ),
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
        if (Array.isArray(existingPayments) && existingPayments.length > 1) {
          const lines = (existingPayments as { payment_method_id?: number; amount?: string }[])
            .filter((payment) => payment.payment_method_id != null)
            .map((payment) => ({
              methodId: Number(payment.payment_method_id),
              amount: String(payment.amount ?? '0'),
            }));
          if (lines.length > 1) {
            return rebalancePaymentLines(lines, total);
          }
        }

        const currentMethod = form?.getFieldValue('paymentMethod');
        const methodId =
          typeof currentMethod === 'object' && currentMethod !== null && 'id' in currentMethod
            ? Number((currentMethod as { id?: number }).id)
            : Number(currentMethod ?? paymentMethods[0]?.id ?? 0) || paymentMethods[0]?.id;

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

    const payload = buildPaymentPayload(paymentLines, paymentMethods, total);
    form?.setFieldValue('payments', payload);

    const primaryMethodId = paymentLines[0]?.methodId;
    if (primaryMethodId != null) {
      form?.setFieldValue('paymentMethod', primaryMethodId);
    }
  }, [form, paymentLines, paymentMethods, total]);

  return (
    <SplitPaymentsField
      paymentMethods={paymentMethods}
      total={total}
      currency={currency}
      value={paymentLines}
      onChange={setPaymentLines}
      variant="admin"
      layout="drawer"
      allowPartial={creditSale}
    />
  );
}
