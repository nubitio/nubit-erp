import { useCallback, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import type { Subscription } from 'rxjs';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
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

  const customersRequestRef = useRef<Subscription | null>(null);
  const lastSelectedCustomerRef = useRef<CustomerOption | null>(null);

  const loadCustomers = useCallback((query: string, page: number, append: boolean) => {
    customersRequestRef.current?.unsubscribe();
    setCustomersLoading(true);

    customersRequestRef.current = httpClient.get<any>(buildCustomersUrl(query, page)).subscribe({
      next: (response) => {
        const list = extractCollection<any>(response.data).map((customer) => ({
          id: Number(customer.id),
          businessName: String(customer.businessName ?? customer.name ?? `Cliente ${customer.id}`),
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
          if (selectedCustomerId === null && !append) {
            const defaultCustomer = list.find((item) => item.id === DEFAULT_CUSTOMER_ID) ?? list[0];
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
      },
      error: () => {
        if (!append) {
          setCustomers([]);
          setCustomersHasMore(false);
        }
      },
    });

    customersRequestRef.current.add(() => setCustomersLoading(false));
  }, [selectedCustomerId]);

  useEffect(() => {
    loadCustomers(deferredCustomerSearch, 1, false);

    return () => {
      customersRequestRef.current?.unsubscribe();
    };
  }, [deferredCustomerSearch, loadCustomers]);

  useEffect(() => {
    if (selectedCustomerId !== null) {
      return;
    }

    const customerInList = customers.find((item) => item.id === DEFAULT_CUSTOMER_ID);
    if (customerInList) {
      setSelectedCustomerId(customerInList.id);
      lastSelectedCustomerRef.current = customerInList;
      setCustomerSearch(
        customerInList.doc ? `${customerInList.businessName} - ${customerInList.doc}` : customerInList.businessName,
      );
      return;
    }

    const subscription = httpClient.get<any>(`${Constants.API_BASE_URL}customers/${DEFAULT_CUSTOMER_ID}`).subscribe({
      next: (response) => {
        const customer = response.data;
        const mapped: CustomerOption = {
          id: Number(customer.id),
          businessName: String(customer.businessName ?? customer.name ?? `Cliente ${customer.id}`),
          doc: customer.ruc ?? customer.dni ?? customer.doc,
        };
        setCustomers((prev) => [mapped, ...prev.filter((item) => item.id !== mapped.id)]);
        setSelectedCustomerId(mapped.id);
        lastSelectedCustomerRef.current = mapped;
        setCustomerSearch(mapped.doc ? `${mapped.businessName} - ${mapped.doc}` : mapped.businessName);
      },
    });

    return () => {
      subscription.unsubscribe();
    };
  }, [customers, selectedCustomerId]);

  const customerOptions = useMemo(() => {
    const selectedInList = customers.find((customer) => customer.id === selectedCustomerId) ?? null;
    const selectedFallback =
      selectedInList ??
      (lastSelectedCustomerRef.current?.id === selectedCustomerId ? lastSelectedCustomerRef.current : null);

    if (!selectedFallback) {
      return customers;
    }

    return [selectedFallback, ...customers.filter((customer) => customer.id !== selectedFallback.id)];
  }, [customers, selectedCustomerId]);

  const selectCustomer = useCallback(
    (nextId: number | null) => {
      setSelectedCustomerId(nextId);
      const selected = customerOptions.find((customer) => customer.id === nextId) ?? null;
      if (selected) {
        lastSelectedCustomerRef.current = selected;
        setCustomerSearch(selected.doc ? `${selected.businessName} - ${selected.doc}` : selected.businessName);
      }
    },
    [customerOptions],
  );

  return {
    customerSearch,
    setCustomerSearch,
    deferredCustomerSearch,
    selectedCustomerId,
    setSelectedCustomerId: selectCustomer,
    customersPage,
    customersHasMore,
    customersLoading,
    customerOptions,
    loadCustomers,
  };
};
