import { FeatureHubLayout } from '@nubitio/admin';

export const CashRegisterHubLayout = () => {
  return (
    <FeatureHubLayout
      density="compact"
      title="Caja"
      subtitle="Estado en vivo, registros por sucursal y categorías de movimiento."
      basePath="/cashbox"
      defaultPath="/cashbox/dashboard"
      tabsAriaLabel="Secciones de caja"
      tabs={[
        { key: 'dashboard', label: 'Cajas', path: '/cashbox/dashboard', icon: 'ph-cash-register', visible: true },
        { key: 'registers', label: 'Registros', path: '/cashbox/registers', icon: 'ph-list-bullets', visible: true },
        { key: 'categories', label: 'Categorías', path: '/cashbox/categories', icon: 'ph-folders', visible: true },
      ]}
    />
  );
};
