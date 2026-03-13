// Catalog feature barrel
// Re-exports route definitions and all public page components organized by sub-feature.
export { catalogRoutes } from './routes';

// Sub-feature barrels (use these for direct page/field imports)
export * from './pages/products';
export * from './pages/customers';
export * from './pages/suppliers';
export * from './pages/inventory';
export * from './pages/pricing';
export * from './pages/taxonomy';
export * from './pages/operations';
export * from './pages/payment-methods';
