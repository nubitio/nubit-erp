import { FeatureHubLayout } from '@nubitio/admin';
import { hasPermission } from '@shared/permissions';

export const CashRegisterHubLayout = () => {
  return (
    <FeatureHubLayout
      density="compact"
      title="Cajas y turnos"
      subtitle="Supervisa las cajas, revisa los turnos y las diferencias de arqueo. El cajero abre y cierra desde el punto de venta."
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
        {
          key: 'control',
          label: 'Control',
          path: '/cashbox/control',
          icon: 'ph-eye',
          visible: hasPermission('activity.view'),
        },
      ]}
    />
  );
};
