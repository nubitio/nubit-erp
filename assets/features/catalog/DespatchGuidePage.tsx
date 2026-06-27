/* eslint-disable react-hooks/refs -- CRUD toolbar callbacks intentionally read Grid refs after user actions, not during render. */
import { useCallback, useMemo, useRef } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { SchemaCrudPage, defineResource, type DataRecord, type GridHandle } from '@nubitio/crud';
import { httpClient } from '@shared/api/HttpClient';
import { httpRequestData } from '@shared/api/network';
import toast from '@shared/lib/SharedUtils';

const API = getCoreApiBaseUrl();

interface DespatchGuideRecord extends DataRecord {
  id: string;
  fullNumber?: string;
  sunatStatus?: string | null;
}

type DespatchSendResponse = {
  message?: string;
  detail?: string;
};

export function DespatchGuidePage() {
  const gridRef = useRef<GridHandle | null>(null);

  const sendSelectedGuide = useCallback(async () => {
    const row = gridRef.current?.getSelectedRow() as DespatchGuideRecord | undefined;
    if (!row?.id) {
      toast('Selecciona una guía de remisión.', 'warning');
      return;
    }

    gridRef.current?.showLoading('Encolando guía de remisión...');
    try {
      const payload = await httpRequestData<DespatchSendResponse>(
        httpClient.post<DespatchSendResponse>(`${API}despatch_guides/${row.id}/send`),
      );
      toast(payload?.message ?? 'Guía de remisión encolada para envío.', 'success');
      gridRef.current?.refresh();
    } catch (error) {
      toast(error instanceof Error ? error.message : 'No se pudo enviar la guía.', 'error');
    } finally {
      gridRef.current?.hideLoading();
    }
  }, []);

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
            onClick: sendSelectedGuide,
          },
        ],
      },
    }),
    [sendSelectedGuide],
  );

  return <SchemaCrudPage resource={resource} gridRef={gridRef} />;
}
