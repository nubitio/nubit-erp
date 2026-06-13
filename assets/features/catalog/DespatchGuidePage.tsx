import { useMemo, useRef } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { SmartCrudPage, defineResource, type DataRecord, type GridHandle } from '@nubitio/crud';
import toast from '@shared/lib/SharedUtils';

const API = getCoreApiBaseUrl();

interface DespatchGuideRecord extends DataRecord {
  id: string;
  fullNumber?: string;
  sunatStatus?: string | null;
}

export function DespatchGuidePage() {
  const gridRef = useRef<GridHandle | null>(null);

  const resource = useMemo(
    () => defineResource<DespatchGuideRecord>(API + 'despatch_guides', {
      id: 'despatchGuide',
      title: 'Guías de remisión',
      viewMode: 'page',
      emptyState: {
        title: 'Sin guías de remisión',
        description: 'Registra y envía guías de remisión electrónica a SUNAT.',
        icon: 'truck',
      },
      toolbar: {
        selection: [
          {
            text: 'Enviar a SUNAT',
            type: 'info',
            group: 'delivery',
            groupLabel: 'Envío',
            onClick: async () => {
              const row = gridRef.current?.getSelectedRow() as DespatchGuideRecord | undefined;
              if (!row?.id) {
                toast('Selecciona una guía de remisión.', 'warning');
                return;
              }

              gridRef.current?.showLoading('Encolando guía de remisión...');
              try {
                const response = await fetch(`${API}despatch_guides/${row.id}/send`, {
                  method: 'POST',
                  credentials: 'include',
                });
                const payload = await response.json().catch(() => null) as { message?: string; detail?: string } | null;
                if (!response.ok) {
                  throw new Error(payload?.detail ?? payload?.message ?? 'No se pudo enviar la guía.');
                }
                toast(payload?.message ?? 'Guía de remisión encolada para envío.', 'success');
                gridRef.current?.refresh();
              } catch (error) {
                toast(error instanceof Error ? error.message : 'No se pudo enviar la guía.', 'error');
              } finally {
                gridRef.current?.hideLoading();
              }
            },
          },
        ],
      },
    }),
    [],
  );

  return <SmartCrudPage resource={resource} gridRef={gridRef} />;
}
