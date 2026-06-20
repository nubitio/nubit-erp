import { useEffect, useMemo, useRef, useState } from 'react';
import { SmartCrudPage, DialogView, defineResource, type ResourceToolbarContext } from '@nubitio/crud';
import { PriceListFields } from './PriceListFields';
import { priceListEndpoints } from './api/priceListEndpoints';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubitio/core';
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
  viewMode: { mode: 'drawer', drawerSize: 'sm' },
  emptyState: {
    title: 'Sin tarifas',
    description: 'Crea una tarifa y asigna precios desde la barra de herramientas.',
    icon: 'tag',
  },
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const pricesRef = useRef<PriceEditorGridHandle | null>(null);
  const [pricesOpen, setPricesOpen] = useState(false);
  const [pricesTitle, setPricesTitle] = useState('Precios');

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
              setPricesTitle('Precios de ' + selectedRow.name);
              setPricesOpen(true);
              // Schedule the ADD after the dialog mounts so the ref is attached
              // and the observer can safely call setRows / showLoading on the child.
              setTimeout(() => {
                emit(priceEvents.ADD, { title: 'Precios de ' + selectedRow.name, row: selectedRow });
              }, 0);
            },
          },
        ],
      }),
    }),
    [emit],
  );

  usePriceListObservers({ on, emit, pricesEvent: priceEvents, pricesRef });

  useEffect(() => {
    const sub = on(priceEvents.SUCCESS, () => setPricesOpen(false));
    return () => sub.unsubscribe();
  }, [on]);

  return (
    <>
      <SmartCrudPage resource={resource} />
      <DialogView
        title={pricesTitle}
        width={760}
        isOpen={pricesOpen}
        onClose={() => setPricesOpen(false)}
        onCancel={() => setPricesOpen(false)}
        onSave={() => emit(priceEvents.SAVE)}
      >
        <PriceEditorGrid ref={pricesRef} />
      </DialogView>
    </>
  );
};
