import { useCallback, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import type { CustomerOption } from './types';
import { useCustomerSearch } from '@shared/hooks/useCustomerSearch';
import { CUSTOMER_PAGE_SIZE } from './posHelpers';

const customerEndpoints = createEndpoints('customers');

const DEFAULT_CUSTOMER_ID = 1;

interface CustomerResponse {
  id: number | string;
  businessName?: string | null;
  name?: string | null;
  ruc?: string | null;
  dni?: string | null;
  doc?: string | null;
  price_list?: { id?: number | string } | null;
}

export const usePosCustomers = () => {
  // Delegate the actual searching + pagination to the shared hook
  const shared = useCustomerSearch({
    pageSize: CUSTOMER_PAGE_SIZE,
  });

  // POS-specific selection state layered on top of the shared search results
  const [selectedCustomerId, setSelectedCustomerIdState] = useState<number | null>(null);
  const lastSelectedCustomerRef = useRef<CustomerOption | null>(null);
  /** When true, the user explicitly cleared the customer — suppress auto-restore */
  const clearedRef = useRef(false);

  const deferredCustomerSearch = useDeferredValue(shared.query);

  // Map shared customers to the shape the old code expected
  const customers: CustomerOption[] = shared.customers;

  // Legacy pagination fields (we still expose them for the current UI)
  const customersPage = 1; // The shared hook manages this internally
  const customersHasMore = shared.hasMore;
  const customersLoading = shared.isLoading;

  // Stable ref to shared.setQuery so it doesn't appear in effect deps and cause re-runs
  const setQueryRef = useRef(shared.setQuery);
  useEffect(() => {
    setQueryRef.current = shared.setQuery;
  }, [shared.setQuery]);

  // Track whether we've already fetched (or attempted) the default customer to avoid
  // re-firing the request every time the `customers` array reference changes.
  const defaultFetchedRef = useRef(false);

  // Keep the same auto-default "Consumidor Final" behavior the POS has always had
  useEffect(() => {
    if (selectedCustomerId !== null || clearedRef.current) {
      return;
    }

    const customerInList = customers.find((item) => item.id === DEFAULT_CUSTOMER_ID);
    if (customerInList) {
      queueMicrotask(() => setSelectedCustomerIdState(customerInList.id));
      lastSelectedCustomerRef.current = customerInList;
      setQueryRef.current(
        customerInList.doc
          ? `${customerInList.businessName} - ${customerInList.doc}`
          : customerInList.businessName,
      );
      return;
    }

    // Only fetch once — don't repeat on every render / customers array refresh
    if (defaultFetchedRef.current) {
      return;
    }
    defaultFetchedRef.current = true;

    // If the default customer isn't in the current page, fetch it directly
    let cancelled = false;
    const controller = new AbortController();

    httpClient.get<CustomerResponse>(customerEndpoints.detail(DEFAULT_CUSTOMER_ID), { signal: controller.signal })
      .then(({ data: customer }) => {
        if (cancelled) return;
        const mapped: CustomerOption = {
          id: Number(customer.id),
          businessName: String(
            customer.businessName ?? customer.name ?? `Cliente ${customer.id}`,
          ),
          doc: customer.ruc ?? customer.dni ?? customer.doc ?? undefined,
          priceListId: customer.price_list?.id != null ? Number(customer.price_list.id) : undefined,
        };
        // We don't mutate the shared hook's list here — we just select it
        setSelectedCustomerIdState(mapped.id);
        lastSelectedCustomerRef.current = mapped;
        setQueryRef.current(
          mapped.doc ? `${mapped.businessName} - ${mapped.doc}` : mapped.businessName,
        );
      })
      .catch(() => {
        /* ignore – default customer fetch failed; POS remains functional without it */
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [customers, selectedCustomerId]);

  /* eslint-disable react-hooks/refs -- lastSelectedCustomerRef is an intentional mutable cache */
  const customerOptions = useMemo(() => {
    const selectedInList = customers.find((customer) => customer.id === selectedCustomerId) ?? null;
    const lastSelected = lastSelectedCustomerRef.current;
    const selectedFallback =
      selectedInList ?? (lastSelected?.id === selectedCustomerId ? lastSelected : null);

    if (!selectedFallback) {
      return customers;
    }

    return [
      selectedFallback,
      ...customers.filter((customer) => customer.id !== selectedFallback.id),
    ];
  }, [customers, selectedCustomerId]);
  /* eslint-enable react-hooks/refs */

  const selectCustomer = useCallback(
    (nextId: number | null) => {
      clearedRef.current = false;
      setSelectedCustomerIdState(nextId);

      const selected = customerOptions.find((customer) => customer.id === nextId) ?? null;
      if (selected) {
        lastSelectedCustomerRef.current = selected;
        shared.setQuery(
          selected.doc ? `${selected.businessName} - ${selected.doc}` : selected.businessName,
        );
      } else {
        shared.setQuery('');
      }
    },
    [customerOptions, shared],
  );

  /** Call this when the user explicitly removes the customer. Prevents auto-restore. */
  const clearSelectedCustomer = useCallback(() => {
    clearedRef.current = true;
    setSelectedCustomerIdState(null);
    shared.setQuery('');
    lastSelectedCustomerRef.current = null;
  }, [shared]);

  // Legacy `loadCustomers` callback expected by the current POS UI.
  // We adapt it to the shared hook.
  const loadCustomers = useCallback(
    (query: string, _page: number, append: boolean) => {
      if (append) {
        shared.loadMore?.();
      } else {
        shared.setQuery(query);
      }
    },
    [shared],
  );

  /* eslint-disable react-hooks/refs */
  return {
    customerSearch: shared.query,
    setCustomerSearch: shared.setQuery,
    deferredCustomerSearch,
    selectedCustomerId,
    setSelectedCustomerId: selectCustomer,
    clearSelectedCustomer,
    customersPage,
    customersHasMore,
    customersLoading,
    customerOptions,
    loadCustomers,
  };
  /* eslint-enable react-hooks/refs */
};
