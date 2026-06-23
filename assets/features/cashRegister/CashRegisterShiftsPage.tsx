/* eslint-disable react-hooks/refs -- Toolbar handlers intentionally read Grid refs inside callbacks, not during render. */
import { useCallback, useMemo, useRef } from 'react';
import { useParams } from 'react-router-dom';
import {
  ColumnPresetSelector,
  DataGridView,
  useColumnPreset,
  type GridHandle,
  type ResourceConfig,
} from '@nubitio/crud';
import { getCoreApiBaseUrl, useCoreTranslation } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { createCashRegisterShiftsToolbar } from '@features/cashRegister';
import { CashRegisterShiftsFields } from './CashRegisterShiftsFields';

type CashRegisterShiftParams = {
  id: string;
};

const CASH_REGISTER_SHIFTS_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['status', 'openedAt', 'closedAt', 'openingAmount', 'expectedAmount'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'status',
      'openedAt',
      'closedAt',
      'openingAmount',
      'expectedAmount',
      'countedAmount',
      'differenceAmount',
      'closeNote',
    ],
  },
];

const CASH_REGISTER_SHIFTS_PRESET_RESOURCE: ResourceConfig = {
  id: 'cash-register-shifts',
  title: 'Historial de turnos',
  apiUrl: '',
  defaultPreset: 'basic',
  columnPresets: CASH_REGISTER_SHIFTS_COLUMN_PRESETS,
};

export const CashRegisterShiftsPage = () => {
  const { id } = useParams<CashRegisterShiftParams>();
  const { t } = useCoreTranslation();
  const gridRef = useRef<GridHandle>(null);
  const presetState = useColumnPreset(CASH_REGISTER_SHIFTS_PRESET_RESOURCE);
  const fields = useMemo(() => CashRegisterShiftsFields(), []);
  const API_URL = `${API}cash-registers/${id}/shifts`;

  const toolbar = createCashRegisterShiftsToolbar({
    onExportPdf: () => {
      const row = gridRef.current?.getSelectedRow();
      if (!row) return;
      window.open(`${API}cash-sessions/${row.id}/closure-report`, '_blank');
    },
  });

  const renderPresetSelector = useCallback(
    () => (
      <ColumnPresetSelector
        resourceId={CASH_REGISTER_SHIFTS_PRESET_RESOURCE.id}
        presets={CASH_REGISTER_SHIFTS_COLUMN_PRESETS}
        activePreset={presetState.activePreset}
        onPresetChange={presetState.setPreset}
        columnsLabel={t('crudPage.columnsLabel')}
        allColumnsLabel={t('crudPage.allColumns')}
      />
    ),
    [presetState.activePreset, presetState.setPreset, t],
  );

  return (
    <div id="wrapper">
      <DataGridView
        ref={gridRef}
        id="cash-register-shifts"
        title="Historial de turnos"
        url={API_URL}
        selectionMode="single"
        fields={fields}
        events={{}}
        toolbar={toolbar}
        hasBackButton
        visibleColumns={presetState.visibleColumns}
        beforeToolbar={renderPresetSelector}
        emptyState={{
          title: 'Sin turnos registrados',
          description: 'Los turnos de caja aparecerán aquí cuando se abra y cierre la caja.',
          icon: 'clock',
        }}
      />
    </div>
  );
};
