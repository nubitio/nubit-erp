import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getCoreApiBaseUrl, useMercureSubscription } from '@nubitio/core';
import * as api from './restaurantApi';
import type { OrderMock, RestaurantTableMock } from '../types';

const API = getCoreApiBaseUrl();

const TABLES_KEY = ['restaurant', 'tables'];
const ORDERS_KEY = ['restaurant', 'orders'];
const MENU_KEY = ['restaurant', 'menu'];
const AREAS_KEY = ['restaurant', 'areas'];

// Both entities carry `mercure: true` (see RestaurantTable/RestaurantOrder),
// so `useMercureSubscription` gets near-instant invalidation on every
// create/update/delete. The poll below is only a safety net for the rare
// case the Mercure hub is unreachable — long enough to not duplicate what
// Mercure already does, short enough that the app self-heals on its own.
const POLL_FALLBACK_MS = 20_000;

export function useRestaurantMenu() {
  return useQuery({
    queryKey: MENU_KEY,
    queryFn: api.fetchMenu,
    staleTime: 5 * 60 * 1000,
  });
}

export function useRestaurantAreas() {
  return useQuery({
    queryKey: AREAS_KEY,
    queryFn: api.fetchAreas,
    staleTime: 60 * 1000,
  });
}

function useRawTables() {
  return useQuery({
    queryKey: TABLES_KEY,
    queryFn: api.fetchTables,
    refetchInterval: POLL_FALLBACK_MS,
  });
}

function useRawOrders() {
  return useQuery({
    queryKey: ORDERS_KEY,
    queryFn: api.fetchOrders,
    refetchInterval: POLL_FALLBACK_MS,
  });
}

interface RestaurantData {
  tables: RestaurantTableMock[];
  orders: Record<string, OrderMock>;
  isLoading: boolean;
}

/**
 * Joins tables + open orders the way the prototype's mock store used to keep
 * them together (`table.currentOrderId` + an `orders` lookup by id).
 */
export function useRestaurantData(): RestaurantData {
  const queryClient = useQueryClient();
  const tablesQuery = useRawTables();
  const ordersQuery = useRawOrders();

  useMercureSubscription(`${API}restaurant/tables`, () => {
    void queryClient.invalidateQueries({ queryKey: TABLES_KEY });
  });
  useMercureSubscription(`${API}restaurant/orders`, () => {
    void queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
  });

  const tables = useMemo<RestaurantTableMock[]>(() => {
    const rawTables = tablesQuery.data ?? [];
    const openOrderIdByTableId = ordersQuery.data?.openOrderIdByTableId ?? new Map<string, string>();
    return rawTables.map((table) => ({
      ...table,
      currentOrderId: openOrderIdByTableId.get(table.id) ?? null,
    }));
  }, [tablesQuery.data, ordersQuery.data]);

  const orders = useMemo<Record<string, OrderMock>>(() => {
    const byId: Record<string, OrderMock> = {};
    for (const order of ordersQuery.data?.orders ?? []) {
      byId[order.id] = order;
    }
    return byId;
  }, [ordersQuery.data]);

  return {
    tables,
    orders,
    isLoading: tablesQuery.isLoading || ordersQuery.isLoading,
  };
}

function useInvalidateRestaurant() {
  const queryClient = useQueryClient();
  return () => {
    void queryClient.invalidateQueries({ queryKey: TABLES_KEY });
    void queryClient.invalidateQueries({ queryKey: ORDERS_KEY });
  };
}

export function useOpenTable() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: (tableId: string) => api.openTable(tableId),
    onSuccess: invalidate,
  });
}

export function useAddOrderItem() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: ({
      orderId,
      productId,
      qty,
      notes,
    }: {
      orderId: string;
      productId: string;
      qty: number;
      notes?: string;
    }) => api.addOrderItem(orderId, productId, qty, notes),
    onSuccess: invalidate,
  });
}

export function useSendToKitchen() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: (orderId: string) => api.sendToKitchen(orderId),
    onSuccess: invalidate,
  });
}

export function useRequestBill() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: (orderId: string) => api.requestBill(orderId),
    onSuccess: invalidate,
  });
}

export function useCloseOrder() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: ({ orderId, saleId }: { orderId: string; saleId: string }) => api.closeOrder(orderId, saleId),
    onSuccess: invalidate,
  });
}

export function useMarkItemReady() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: (itemId: string) => api.markItemReady(itemId),
    onSuccess: invalidate,
  });
}

export function useMarkItemDelivered() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: (itemId: string) => api.markItemDelivered(itemId),
    onSuccess: invalidate,
  });
}

export function useUpdateTablePosition() {
  const invalidate = useInvalidateRestaurant();
  return useMutation({
    mutationFn: ({ tableId, x, y }: { tableId: string; x: number; y: number }) =>
      api.updateTablePosition(tableId, x, y),
    onSuccess: invalidate,
  });
}
