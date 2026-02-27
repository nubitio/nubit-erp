import { useEffect, useState } from 'react';
import type { Subscription } from 'rxjs';
import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@platform/common/Constants';
import type { Category, PaymentMethodOption, PosItem, WarehouseOption } from './types';
import { extractCollection, isCashPaymentMethod, normalizeProduct } from './posHelpers';

type UsePosCatalogDataParams = {
  currentBranchId: string;
};

export const usePosCatalogData = ({ currentBranchId }: UsePosCatalogDataParams) => {
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [menuData, setMenuData] = useState<PosItem[]>([]);
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethodOption[]>([]);
  const [selectedPaymentMethodId, setSelectedPaymentMethodId] = useState<number | null>(null);
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([]);
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>('');

  useEffect(() => {
    setLoading(true);
    setLoadError(null);

    const subscriptions: Subscription[] = [];
    let categoriesLoaded = false;
    let productsLoaded = false;

    const markFinishedIfReady = () => {
      if (categoriesLoaded && productsLoaded) {
        setLoading(false);
      }
    };

    subscriptions.push(
      httpClient.get<any>(`${Constants.API_BASE_URL}payment-methods?itemsPerPage=100&page=1`).subscribe({
        next: (response) => {
          const list = extractCollection<any>(response.data).map((method) => ({
            id: Number(method.id),
            name: String(method.name ?? `Metodo ${method.id}`),
          }));
          setPaymentMethods(list);
          if (list.length > 0) {
            const cash = list.find((item) => isCashPaymentMethod(item));
            setSelectedPaymentMethodId((prev) => prev ?? cash?.id ?? list[0].id);
          }
        },
      }),
    );

    subscriptions.push(
      httpClient.get<any>(`${Constants.API_BASE_URL}warehouses?itemsPerPage=100&page=1`).subscribe({
        next: (response) => {
          const list = extractCollection<any>(response.data).map((warehouse) => ({
            id: String(warehouse.id ?? warehouse.almacen_id),
            name: String(warehouse.name ?? warehouse.almacen ?? 'Almacen'),
            branch: warehouse.branch,
          }));

          const filteredByBranch =
            currentBranchId === ''
              ? list
              : list.filter(
                  (warehouse) => String(warehouse.branch?.id ?? '') === currentBranchId || !warehouse.branch?.id,
                );

          const finalList = filteredByBranch.length > 0 ? filteredByBranch : list;
          setWarehouses(finalList);
          if (finalList.length > 0) {
            setSelectedWarehouseId((prev) => prev || finalList[0].id);
          }
        },
      }),
    );

    subscriptions.push(
      httpClient.get<Category[]>(`${Constants.API_BASE_URL}categories`).subscribe({
        next: (response) => {
          setCategories(response.data ?? []);
          categoriesLoaded = true;
          markFinishedIfReady();
        },
        error: () => {
          setCategories([]);
          categoriesLoaded = true;
          markFinishedIfReady();
        },
      }),
    );

    subscriptions.push(
      httpClient.get<PosItem[]>(`${Constants.API_BASE_URL}products?itemsPerPage=9999`).subscribe({
        next: (response) => {
          const products = (response.data ?? []).map(normalizeProduct);
          setMenuData(products);

          setCategories((prev) => {
            if (prev.length > 0) {
              return prev;
            }

            const categoryMap = new Map<string, Category>();
            products.forEach((product) => {
              if (product.category?.id) {
                categoryMap.set(product.category.id, product.category);
              }
            });

            return Array.from(categoryMap.values());
          });

          productsLoaded = true;
          markFinishedIfReady();
        },
        error: () => {
          setLoadError('No se pudo cargar el catálogo para el POS');
          productsLoaded = true;
          markFinishedIfReady();
        },
      }),
    );

    return () => {
      subscriptions.forEach((subscription) => subscription.unsubscribe());
    };
  }, [currentBranchId]);

  return {
    loading,
    loadError,
    categories,
    menuData,
    paymentMethods,
    selectedPaymentMethodId,
    setSelectedPaymentMethodId,
    warehouses,
    selectedWarehouseId,
    setSelectedWarehouseId,
  };
};
