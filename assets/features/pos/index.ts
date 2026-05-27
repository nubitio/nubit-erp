// POS feature barrel — public API
// Page
export { POSPage } from './POSPage';

// Public hooks
export { usePosCheckout } from './usePosCheckout';
export { usePosCustomers } from './usePosCustomers';
export { usePosCatalogData } from './usePosCatalogData';
export { useWarehouseStock } from './useWarehouseStock';

// Public types
export type {
  PosItem,
  CartItem,
  CustomerOption,
  PaymentMethodOption,
  WarehouseOption,
  Category,
} from './types';

// Routes
export { posRoutes } from './routes';
