import { useCallback, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import type { CustomerOption } from './types';
import { useCustomerSearch } from '@shared/hooks/useCustomerSearch';
import { CUSTOMER_PAGE_SIZE } from './posHelpers';

const DEFAULT_CUSTOMER_ID = 1;

interface CustomerResponse {
  id: number | string;
  businessName?: string | null;
  name?: string | null;
  ruc?: string | null;
  dni?: string | null;
  doc?: string | null;
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

  // Keep the same auto-default "Consumidor Final" behavior the POS has always had
  useEffect(() => {
    if (selectedCustomerId !== null || clearedRef.current) {
      return;
    }

    const customerInList = customers.find((item) => item.id === DEFAULT_CUSTOMER_ID);
    if (customerInList) {
      setSelectedCustomerIdState(customerInList.id);
      lastSelectedCustomerRef.current = customerInList;
      shared.setQuery(
        customerInList.doc
          ? `${customerInList.businessName} - ${customerInList.doc}`
          : customerInList.businessName,
      );
      return;
    }

    // If the default customer isn't in the current page, fetch it directly
    let cancelled = false;
    const controller = new AbortController();

    httpClient.get<CustomerResponse>(`customers/${DEFAULT_CUSTOMER_ID}`, { signal: controller.signal })
      .then(({ data: customer }) => {
        if (cancelled) return;
        const mapped: CustomerOption = {
          id: Number(customer.id),
          businessName: String(
            customer.businessName ?? customer.name ?? `Cliente ${customer.id}`,
          ),
          doc: customer.ruc ?? customer.dni ?? customer.doc ?? undefined,
        };
        // We don't mutate the shared hook's list here — we just select it
        setSelectedCustomerIdState(mapped.id);
        lastSelectedCustomerRef.current = mapped;
        shared.setQuery(
          mapped.doc ? `${mapped.businessName} - ${mapped.doc}` : mapped.businessName,
        );
      })
      .catch(() => {
        /* ignore network errors for the default customer fallback */
      });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [customers, selectedCustomerId, shared]);

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
