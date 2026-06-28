import { OperationCardGrid } from '@nubitio/ui';
import { useNavigate } from 'react-router-dom';
import { useTenantRuntimeConfig } from '@shared/hooks';

export const InventoryOperationsPage = () => {
  const navigate = useNavigate();
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const showTransfers = runtimeConfig?.ui.inventory.showTransfers ?? false;

  return (
    <div className="inventory-operations-page">
      <OperationCardGrid
        testId="inventory-operations"
        title="Operaciones de stock"
        description="Cada movimiento genera un documento inmutable en el kardex."
        operations={[
          {
            key: 'receipt',
            title: 'Entrada manual',
            description: 'Mercadería recibida sin compra formal.',
            icon: 'ph-arrow-line-down',
            accent: 'in',
            testId: 'inventory-operation-receipt',
            onClick: () => navigate('/receipt/new'),
          },
          {
            key: 'cycle-count',
            title: 'Conteo cíclico',
            description: 'Cuenta varios productos en un solo documento.',
            icon: 'ph-clipboard-text',
            accent: 'count',
            testId: 'inventory-operation-cycle-count',
            onClick: () => navigate('/inventory/cycle-count'),
          },
          {
            key: 'adjustment',
            title: 'Ajuste de stock',
            description: 'Corrección por merma, daño o vencimiento.',
            icon: 'ph-scales',
            accent: 'adjust',
            testId: 'inventory-operation-adjustment',
            onClick: () => navigate('/stock-adjustment/new'),
          },
          {
            key: 'outbound',
            title: 'Salida manual',
            description: 'Consumo interno, muestras o baja documentada.',
            icon: 'ph-arrow-line-up',
            accent: 'out',
            testId: 'inventory-operation-outbound',
            onClick: () => navigate('/outbound/new'),
          },
          ...(showTransfers
            ? [{
                key: 'transfer',
                title: 'Transferencia',
                description: 'Mueve stock entre almacenes.',
                icon: 'ph-arrows-left-right',
                accent: 'transfer' as const,
                testId: 'inventory-operation-transfer',
                onClick: () => navigate('/transfer/new'),
              }]
            : []),
        ]}
      />
    </div>
  );
};