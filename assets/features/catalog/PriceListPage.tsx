import { useMemo, useRef } from 'react';
import { SmartCrudPage, DialogView, defineResource, type DataRecord } from '@nubit/crud';
import { PriceListFields } from './PriceListFields';
import { priceListEndpoints } from './api/priceListEndpoints';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubit/core';
import { usePriceListObservers } from './model/usePriceListObservers';
import { priceListEvents, priceEvents } from '@app/domainEvents';
import {
  PriceEditorGrid,
  type PriceEditorGridHandle,
} from './components/PriceEditorGrid';

const baseResource = defineResource({
  id: 'price-list',
  title: 'Tarifas',
  apiUrl: priceListEndpoints.list(),
  fields: PriceListFields(),
  events: priceListEvents,
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const selectedRowRef = useRef<DataRecord | null>(null);
  const pricesRef = useRef<PriceEditorGridHandle | null>(null);

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: [
        {
          text: 'Precios',
          type: 'info',
          onClick: () => {
            if (!selectedRowRef.current) {
              toast('Debe seleccionar una tarifa', 'warning');
              return;
            }
            emit(priceEvents.ADD, { title: 'Precios de ' + selectedRowRef.current.name });
          },
        },
      ],
    }),
    [emit],
  );

  usePriceListObservers({ on, emit, pricesEvent: priceEvents, selectedRowRef, pricesRef });

  return (
    <>
      <SmartCrudPage
        resource={resource}
        onSelectionChanged={(rows) => {
          selectedRowRef.current = rows[0] ?? null;
        }}
      />
      <DialogView events={priceEvents}>
        <PriceEditorGrid ref={pricesRef} />
      </DialogView>
    </>
  );
};
