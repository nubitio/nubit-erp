import { useCallback, useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import type { CustomerOption } from './types';
import { mapPosCustomer } from './lib/mapPosCustomer';

const customerEndpoints = createEndpoints('customers');
const ANONYMOUS_CUSTOMER_RUC = '00000000';

export const usePosCustomers = () => {
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null);
  const [selectedCustomer, setSelectedCustomer] = useState<CustomerOption | null>(null);
  const [priceListId, setPriceListId] = useState<number | null>(null);
  const clearedRef = useRef(false);

  const applyCustomer = useCallback((
    mapped: CustomerOption | null,
    preserveKnownPriceList = false,
  ) => {
    if (!mapped) {
      return;
    }

    setSelectedCustomerId(mapped.id);
    setSelectedCustomer(mapped);
    setPriceListId((current) =>
      preserveKnownPriceList && mapped.priceListId == null
        ? current
        : (mapped.priceListId ?? null),
    );
  }, []);

  useEffect(() => {
    if (selectedCustomerId !== null || clearedRef.current) {
      return;
    }

    let cancelled = false;
    const controller = new AbortController();

    httpClient
      .get<unknown>(`${customerEndpoints.list()}?ruc=${ANONYMOUS_CUSTOMER_RUC}`, { signal: controller.signal })
      .then(({ data }) => {
        if (cancelled) return;
        const raw = data as { member?: unknown[]; 'hydra:member'?: unknown[] };
        const first = raw.member?.[0] ?? raw['hydra:member']?.[0] ?? (Array.isArray(data) ? data[0] : data);
        const mapped = mapPosCustomer(first);
        if (mapped && String((first as { ruc?: string } | undefined)?.ruc ?? '') === ANONYMOUS_CUSTOMER_RUC) {
          applyCustomer(mapped);
        }
      })
      .catch(() => {
        /* anonymous customer fetch failed — POS remains usable as walk-in */
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [applyCustomer, selectedCustomerId]);

  const clearSelectedCustomer = useCallback(() => {
    clearedRef.current = true;
    setSelectedCustomerId(null);
    setSelectedCustomer(null);
    setPriceListId(null);
  }, []);

  // Completing a sale starts again with the configured walk-in customer. This
  // differs from an explicit user clear, which must leave the field empty.
  const resetSelectedCustomer = useCallback(() => {
    clearedRef.current = false;
    setSelectedCustomerId(null);
    setSelectedCustomer(null);
    setPriceListId(null);
  }, []);

  const selectCustomer = useCallback((value: string | null, item?: unknown) => {
    if (!value) {
      clearSelectedCustomer();
      return;
    }

    clearedRef.current = false;
    const id = Number(value);
    const mappedFromItem = mapPosCustomer(item);

    setSelectedCustomerId(id);
    setSelectedCustomer(
      mappedFromItem ?? {
        id,
        businessName: `Cliente ${id}`,
      },
    );
    setPriceListId(mappedFromItem?.priceListId ?? null);

    void httpClient
      .get<unknown>(`${customerEndpoints.detail(id)}?_=${Date.now()}`)
      .then(({ data }) => {
        // Some customer detail projections omit the relation even though the
        // search result included it. Do not erase a tariff we already resolved
        // from the selected option in that case.
        applyCustomer(mapPosCustomer(data), true);
      })
      .catch(() => {
        /* keep the lightweight selection if detail fetch fails */
      });
  }, [applyCustomer, clearSelectedCustomer]);

  return {
    selectedCustomerId,
    selectedCustomer,
    priceListId,
    selectCustomer,
    clearSelectedCustomer,
    resetSelectedCustomer,
  };
};
