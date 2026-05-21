import { useRef } from 'react';
import { DataGridView, DialogView, FormView, type GridHandle } from '@nubit/crud';
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

const API_URL = priceListEndpoints.list();
const fields = PriceListFields();

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<GridHandle | null>(null);
  const pricesRef = useRef<PriceEditorGridHandle | null>(null);

  const toolbar = [
    {
      text: 'Precios',
      type: 'info',
      onClick: () => {
        const selectedRow = gridRef.current?.getSelectedRow();
        if (!selectedRow) {
          toast('Debe seleccionar una tarifa', 'warning');
          return;
        }
        emit(priceEvents.ADD, { title: 'Precios de ' + selectedRow.name });
      },
    },
  ];

  usePriceListObservers({
    on,
    emit,
    pricesEvent: priceEvents,
    gridRef,
    pricesRef,
  });

  return (
    <div id="wrapper">
      <DataGridView
        id="price-list"
        ref={gridRef}
        title="Tarifas"
        toolbar={toolbar}
        url={API_URL}
        allowAdd
        allowEdit
        allowDelete
        fields={fields}
        events={priceListEvents}
      />
      <DialogView events={priceListEvents}>
        <FormView url={API_URL} fields={fields} events={priceListEvents} />
      </DialogView>
      <DialogView events={priceEvents}>
        <PriceEditorGrid ref={pricesRef} />
      </DialogView>
    </div>
  );
};
