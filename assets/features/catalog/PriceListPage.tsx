import { useMemo, useRef } from 'react';
import { SmartCrudPage, DialogView, defineResource, type ResourceToolbarContext } from '@nubit/crud';
import { PriceListFields } from './PriceListFields';
import { priceListEndpoints } from './api/priceListEndpoints';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubit/core';
import { usePriceListObservers } from './model/usePriceListObservers';
import { priceEvents } from '@app/domainEvents';
import {
  PriceEditorGrid,
  type PriceEditorGridHandle,
} from './components/PriceEditorGrid';

const baseResource = defineResource(priceListEndpoints.list(), {
  id: 'price-list',
  title: 'Tarifas',
  fields: PriceListFields(),
  viewMode: { mode: 'drawer', drawerWidth: 560 },
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const pricesRef = useRef<PriceEditorGridHandle | null>(null);

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: ({ selectedRow }: ResourceToolbarContext) => ({
        selection: [
          {
            text: 'Precios',
            type: 'info',
            group: 'edit',
            groupLabel: 'Edición',
            onClick: () => {
              if (!selectedRow) {
                toast('Debe seleccionar una tarifa', 'warning');
                return;
              }
              emit(priceEvents.ADD, { title: 'Precios de ' + selectedRow.name, row: selectedRow });
            },
          },
        ],
      }),
    }),
    [emit],
  );

  usePriceListObservers({ on, emit, pricesEvent: priceEvents, pricesRef });

  return (
    <>
      <SmartCrudPage resource={resource} />
      <DialogView events={priceEvents}>
        <PriceEditorGrid ref={pricesRef} />
      </DialogView>
    </>
  );
};
