/**
 * Shared customer search hook.
 *
 * Encapsulates paginated customer search with abort-controller cancellation.
 * Used by both the POS Workspace (via usePosCustomers) and future shared
 * CustomerSelector components.
 *
 * Does NOT include POS-specific behavior such as default customer auto-selection
 * or "Consumidor Final" fallback — those concerns live in usePosCustomers.
 */

import { useCallback, useDeferredValue, useEffect, useRef, useState } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export interface CustomerSearchOption {
  id: number;
  businessName: string;
  /** RUC (11 digits) or DNI (8 digits). */
  doc?: string;
}

export interface UseCustomerSearchOptions {
  /** Items per page. Defaults to 50. */
  pageSize?: number;
  /**
   * Additional query params to append on every request (e.g. `{ active: 'true' }`).
   * These are merged with the search/pagination params.
   */
  extraParams?: Record<string, string>;
}

export interface UseCustomerSearchResult {
  /** Debounced search query string (use for display). */
  query: string;
  /** Update the search query string. Triggers a debounced fetch. */
  setQuery: (value: string) => void;
  /** Current page of results (reset to 1 on each new query). */
  customers: CustomerSearchOption[];
  /** Whether more pages are available. */
  hasMore: boolean;
  /** Whether a fetch is in flight. */
  isLoading: boolean;
  /** Load the next page (appends to existing results). */
  loadMore: () => void;
  /** Reset results and re-fetch from page 1 with the current query. */
  refresh: () => void;
}

function buildUrl(
  query: string,
  page: number,
  pageSize: number,
  extraParams?: Record<string, string>,
): string {
  const params = new URLSearchParams();
  params.set('itemsPerPage', String(pageSize));
  params.set('page', String(page));
  params.append('searchExpr[]', 'businessName');
  params.append('searchExpr[]', 'ruc');

  const trimmed = query.trim();
  if (trimmed) {
    params.set('searchValue', trimmed);
  }

  if (extraParams) {
    for (const [k, v] of Object.entries(extraParams)) {
      params.set(k, v);
    }
  }

  return `${API}customers?${params.toString()}`;
}

function extractCustomers(raw: unknown): CustomerSearchOption[] {
  let items: unknown[] = [];

  if (Array.isArray(raw)) {
    items = raw;
  } else if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>;
    if (Array.isArray(obj['hydra:member'])) items = obj['hydra:member'] as unknown[];
    else if (Array.isArray(obj['items'])) items = obj['items'] as unknown[];
    else if (Array.isArray(obj['data'])) items = obj['data'] as unknown[];
  }

  return items.map((c) => {
    const customer = c as Record<string, unknown>;
    return {
      id: Number(customer['id']),
      businessName: String(
        customer['businessName'] ?? customer['name'] ?? `Cliente ${customer['id']}`,
      ),
      doc: (customer['ruc'] ?? customer['dni'] ?? customer['doc']) as string | undefined,
    };
  });
}

export function useCustomerSearch(
  options: UseCustomerSearchOptions = {},
): UseCustomerSearchResult {
  const pageSize = options.pageSize ?? 50;
  const extraParams = options.extraParams;

  const [query, setQuery] = useState('');
  const deferredQuery = useDeferredValue(query);

  const [customers, setCustomers] = useState<CustomerSearchOption[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const abortRef = useRef<AbortController | null>(null);

  const fetchPage = useCallback(
    (searchQuery: string, pageNum: number, append: boolean) => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setIsLoading(true);

      const url = buildUrl(searchQuery, pageNum, pageSize, extraParams);

      httpClient
        .get<unknown>(url, { signal: controller.signal })
        .then((response) => {
          if (controller.signal.aborted) return;

          const list = extractCustomers(response.data);

          setCustomers((prev) => {
            const next = append ? [...prev, ...list] : list;
            // Deduplicate by id, preserving order.
            const seen = new Set<number>();
            return next.filter((c) => {
              if (seen.has(c.id)) return false;
              seen.add(c.id);
              return true;
            });
          });

          setPage(pageNum);
          setHasMore(list.length >= pageSize);
        })
        .catch(() => {
          if (controller.signal.aborted) return;
          if (!append) {
            setCustomers([]);
            setHasMore(false);
          }
        })
        .finally(() => {
          if (!controller.signal.aborted) setIsLoading(false);
        });
    },
    [pageSize, extraParams],
  );

  // Re-fetch from page 1 whenever the debounced query changes.
  useEffect(() => {
    fetchPage(deferredQuery, 1, false);

    return () => {
      abortRef.current?.abort();
    };
  }, [deferredQuery, fetchPage]);

  const loadMore = useCallback(() => {
    if (!hasMore || isLoading) return;
    fetchPage(deferredQuery, page + 1, true);
  }, [hasMore, isLoading, deferredQuery, page, fetchPage]);

  const refresh = useCallback(() => {
    fetchPage(deferredQuery, 1, false);
  }, [deferredQuery, fetchPage]);

  return {
    query,
    setQuery,
    customers,
    hasMore,
    isLoading,
    loadMore,
    refresh,
  };
}
