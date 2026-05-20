import { useCallback, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';
import type { CustomerOption } from './types';
import { buildCustomersUrl, extractCollection, CUSTOMER_PAGE_SIZE } from './posHelpers';

const DEFAULT_CUSTOMER_ID = 1;

export const usePosCustomers = () => {
  const [customers, setCustomers] = useState<CustomerOption[]>([]);
  const [customerSearch, setCustomerSearch] = useState('');
  const deferredCustomerSearch = useDeferredValue(customerSearch);
  const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(null);
  const [customersPage, setCustomersPage] = useState(1);
  const [customersHasMore, setCustomersHasMore] = useState(false);
  const [customersLoading, setCustomersLoading] = useState(false);

  const customersRequestRef = useRef<AbortController | null>(null);
  const lastSelectedCustomerRef = useRef<CustomerOption | null>(null);
  /** When true, the user explicitly cleared the customer — suppress auto-restore */
  const clearedRef = useRef(false);

  const loadCustomers = useCallback(
    (query: string, page: number, append: boolean) => {
      customersRequestRef.current?.abort();
      const controller = new AbortController();
      customersRequestRef.current = controller;
      setCustomersLoading(true);

      httpClient.get<any>(buildCustomersUrl(query, page), { signal: controller.signal }).then((response) => {
          if (controller.signal.aborted) return;
          const list = extractCollection<any>(response.data).map((customer) => ({
            id: Number(customer.id),
            businessName: String(
              customer.businessName ?? customer.name ?? `Cliente ${customer.id}`,
            ),
            doc: customer.ruc ?? customer.dni ?? customer.doc,
          }));

          setCustomers((prev) => {
            const next = append ? [...prev, ...list] : list;
            const unique = new Map<number, CustomerOption>();
            next.forEach((customer) => unique.set(customer.id, customer));
            return Array.from(unique.values());
          });

          setCustomersPage(page);
          setCustomersHasMore(list.length >= CUSTOMER_PAGE_SIZE);

          if (list.length > 0) {
            if (selectedCustomerId === null && !append && !clearedRef.current) {
              const defaultCustomer =
                list.find((item) => item.id === DEFAULT_CUSTOMER_ID) ?? list[0];
              setSelectedCustomerId(defaultCustomer.id);
              lastSelectedCustomerRef.current = defaultCustomer;
              setCustomerSearch(
                defaultCustomer.doc
                  ? `${defaultCustomer.businessName} - ${defaultCustomer.doc}`
                  : defaultCustomer.businessName,
              );
            } else {
              const currentSelected = list.find((item) => item.id === selectedCustomerId);
              if (currentSelected) {
                lastSelectedCustomerRef.current = currentSelected;
              }
            }
          } else if (!append) {
            setCustomersHasMore(false);
          }
        }).catch(() => {
          if (controller.signal.aborted) return;
          if (!append) {
            setCustomers([]);
            setCustomersHasMore(false);
          }
        }).finally(() => {
          if (!controller.signal.aborted) setCustomersLoading(false);
        });
    },
    [selectedCustomerId],
  );

  useEffect(() => {
    loadCustomers(deferredCustomerSearch, 1, false);

    return () => {
      customersRequestRef.current?.abort();
    };
  }, [deferredCustomerSearch, loadCustomers]);

  useEffect(() => {
    if (selectedCustomerId !== null || clearedRef.current) {
      return;
    }

    const customerInList = customers.find((item) => item.id === DEFAULT_CUSTOMER_ID);
    if (customerInList) {
      setSelectedCustomerId(customerInList.id);
      lastSelectedCustomerRef.current = customerInList;
      setCustomerSearch(
        customerInList.doc
          ? `${customerInList.businessName} - ${customerInList.doc}`
          : customerInList.businessName,
      );
      return;
    }

    let cancelled = false;
    const controller = new AbortController();
    httpClient
      .get<any>(`${API_BASE_URL}customers/${DEFAULT_CUSTOMER_ID}`, { signal: controller.signal })
      .then((response) => {
          if (cancelled) return;
          const customer = response.data;
          const mapped: CustomerOption = {
            id: Number(customer.id),
            businessName: String(
              customer.businessName ?? customer.name ?? `Cliente ${customer.id}`,
            ),
            doc: customer.ruc ?? customer.dni ?? customer.doc,
          };
          setCustomers((prev) => [mapped, ...prev.filter((item) => item.id !== mapped.id)]);
          setSelectedCustomerId(mapped.id);
          lastSelectedCustomerRef.current = mapped;
          setCustomerSearch(
            mapped.doc ? `${mapped.businessName} - ${mapped.doc}` : mapped.businessName,
          );
        });

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [customers, selectedCustomerId]);

  /* eslint-disable react-hooks/refs -- lastSelectedCustomerRef is an intentional mutable cache for
     the last resolved customer entity; reading it inside useMemo provides a stale-value fallback
     when the customer is not yet in the current page. This is a deliberate design choice. */
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
      clearedRef.current = false; // explicit selection resets the cleared flag
      setSelectedCustomerId(nextId);
      const selected = customerOptions.find((customer) => customer.id === nextId) ?? null;
      if (selected) {
        lastSelectedCustomerRef.current = selected;
        setCustomerSearch(
          selected.doc ? `${selected.businessName} - ${selected.doc}` : selected.businessName,
        );
      }
    },
    [customerOptions],
  );

  /** Call this when the user explicitly removes the customer. Prevents auto-restore. */
  const clearSelectedCustomer = useCallback(() => {
    clearedRef.current = true;
    setSelectedCustomerId(null);
    setCustomerSearch('');
    lastSelectedCustomerRef.current = null;
  }, []);

  /* eslint-disable react-hooks/refs -- customerOptions is derived from lastSelectedCustomerRef (intentional cache); taint propagates to return */
  return {
    customerSearch,
    setCustomerSearch,
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
