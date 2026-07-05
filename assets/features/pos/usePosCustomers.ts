import { useCallback, useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import type { CustomerOption } from './types';
import { mapPosCustomer } from './lib/mapPosCustomer';

const customerEndpoints = createEndpoints('customers');
const DEFAULT_CUSTOMER_ID = 1;

export const usePosCustomers = () => {
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null);
  const [selectedCustomer, setSelectedCustomer] = useState<CustomerOption | null>(null);
  const [priceListId, setPriceListId] = useState<number | null>(null);
  const clearedRef = useRef(false);

  const applyCustomer = useCallback((mapped: CustomerOption | null) => {
    if (!mapped) {
      return;
    }

    setSelectedCustomerId(mapped.id);
    setSelectedCustomer(mapped);
    setPriceListId(mapped.priceListId ?? null);
  }, []);

  useEffect(() => {
    if (selectedCustomerId !== null || clearedRef.current) {
      return;
    }

    let cancelled = false;
    const controller = new AbortController();

    httpClient
      .get<unknown>(customerEndpoints.detail(DEFAULT_CUSTOMER_ID), { signal: controller.signal })
      .then(({ data }) => {
        if (cancelled) return;
        applyCustomer(mapPosCustomer(data));
      })
      .catch(() => {
        /* default customer fetch failed — POS remains usable without it */
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
      .get<unknown>(customerEndpoints.detail(id))
      .then(({ data }) => {
        applyCustomer(mapPosCustomer(data));
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
  };
};