import { FeatureHubLayout } from '@nubitio/admin';

export const CashRegisterHubLayout = () => {
  return (
    <FeatureHubLayout
      density="compact"
      className="hub-in-sidebar"
      title="Caja"
      subtitle="Abre el turno, cobra y cierra con arqueo. Si el efectivo no cuadra, se ve aquí."
      basePath="/cashbox"
      defaultPath="/cashbox/dashboard"
      tabsAriaLabel="Secciones de caja"
      tabs={[
        {
          key: 'dashboard',
          label: 'Cajas',
          path: '/cashbox/dashboard',
          icon: 'ph-cash-register',
          visible: true,
        },
        {
          key: 'registers',
          label: 'Registros',
          path: '/cashbox/registers',
          icon: 'ph-list-bullets',
          visible: true,
        },
        {
          key: 'categories',
          label: 'Categorías',
          path: '/cashbox/categories',
          icon: 'ph-folders',
          visible: true,
        },
      ]}
    />
  );
};
