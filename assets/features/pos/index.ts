// POS feature barrel — public API
// Page
export { POSPage } from './pages/POSPage';

// Public hooks
export { usePosCheckout } from './pages/usePosCheckout';
export { usePosCustomers } from './pages/usePosCustomers';
export { usePosCatalogData } from './pages/usePosCatalogData';
export { useWarehouseStock } from './pages/useWarehouseStock';

// Public types
export type {
  PosItem,
  CartItem,
  CustomerOption,
  PaymentMethodOption,
  WarehouseOption,
  Category,
} from './pages/types';

// Routes
export { posRoutes } from './routes';
