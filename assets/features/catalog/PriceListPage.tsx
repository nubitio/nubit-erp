import { useEffect, useMemo, useRef, useState } from 'react';
import {
  DataGridView,
  DialogView,
  HydraAdapter,
  SchemaCrudPage,
  defineResource,
  identityField,
  textField,
  type DataGridSelectionChangedEvent,
  type ResourceToolbarContext,
} from '@nubitio/crud';
import { PriceListFields } from './PriceListFields';
import { priceListEndpoints } from './api/priceListEndpoints';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubitio/core';
import { usePriceListObservers } from './model/usePriceListObservers';
import { priceEvents } from '@app/domainEvents';
import { PriceEditorGrid, type PriceEditorGridHandle } from './components/PriceEditorGrid';
import { customerApiUrl } from '@features/customer/model';
import { httpClient } from '@shared/api/HttpClient';

const customerAssignmentFields = [
  identityField().valueType('number').build(),
  textField().name('ruc').label('RUC/DNI').width(150).build(),
  textField().name('businessName').label('Razón social').build(),
];

const baseResource = defineResource(priceListEndpoints.list(), {
  id: 'price-list',
  title: 'Listas de precios',
  fields: PriceListFields(),
  viewMode: { mode: 'drawer', drawerSize: 'sm' },
  emptyState: {
    title: 'Sin listas de precios',
    description: 'Crea una lista para ofrecer precios preferenciales a tus clientes.',
    icon: 'tag',
  },
  permissions: { canEdit: true, canDelete: true },
  // Special prices are written in bulk by the price editor; each save that changes one
  // leaves a before/after entry here (the trail is read from nubit_audit_log).
  auditTrail: {
    enabled: true,
    apiUrl: (id: string | number) => `/api/audit-trail/pricelist/${id}`,
  },
});

export const PriceListPage = () => {
  const [on, emit] = useEvents();
  const pricesRef = useRef<PriceEditorGridHandle | null>(null);
  const [pricesOpen, setPricesOpen] = useState(false);
  const [pricesTitle, setPricesTitle] = useState('Precios');
  const [assignmentOpen, setAssignmentOpen] = useState(false);
  const [assignmentList, setAssignmentList] = useState<{
    id: string | number;
    name: string;
  } | null>(null);
  const [selectedCustomerIds, setSelectedCustomerIds] = useState<(string | number)[]>([]);
  const [savingAssignment, setSavingAssignment] = useState(false);

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
                emit(priceEvents.ADD, {
                  title: 'Precios de ' + selectedRow.name,
                  row: selectedRow,
                });
              }, 0);
            },
          },
          {
            text: 'Asignar clientes',
            type: 'info',
            group: 'customers',
            groupLabel: 'Clientes',
            onClick: () => {
              if (!selectedRow) {
                toast('Debe seleccionar una tarifa', 'warning');
                return;
              }
              setAssignmentList({
                id: String(selectedRow.id),
                name: String(selectedRow.name ?? ''),
              });
              setSelectedCustomerIds([]);
              setAssignmentOpen(true);
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

  const assignCustomers = async () => {
    if (!assignmentList || savingAssignment) return;
    if (selectedCustomerIds.length === 0) {
      toast('Selecciona al menos un cliente', 'warning');
      return;
    }

    setSavingAssignment(true);
    const priceList = `${priceListEndpoints.list()}/${assignmentList.id}`;
    const results = await Promise.allSettled(
      selectedCustomerIds.map((id) => httpClient.patch(`${customerApiUrl}/${id}`, { priceList })),
    );
    const failed = results.filter((result) => result.status === 'rejected').length;
    const updated = results.length - failed;
    if (failed > 0) {
      toast(`Se asignaron ${updated} clientes; ${failed} no pudieron actualizarse`, 'warning');
    } else {
      toast(
        `${updated} ${updated === 1 ? 'cliente asignado' : 'clientes asignados'} a ${assignmentList.name}`,
        'success',
      );
    }
    setSavingAssignment(false);
    if (updated > 0) setAssignmentOpen(false);
  };

  return (
    <>
      <SchemaCrudPage resource={resource} />
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
      <DialogView
        title={`Asignar clientes a ${assignmentList?.name ?? 'tarifa'}`}
        width={760}
        isOpen={assignmentOpen}
        onClose={() => setAssignmentOpen(false)}
        onCancel={() => setAssignmentOpen(false)}
        onSave={() => void assignCustomers()}
      >
        <p>
          Selecciona los clientes que usarán esta tarifa. La asignación reemplazará su tarifa
          actual.
        </p>
        <DataGridView
          id="price-list-customer-assignment"
          title="Clientes"
          url={customerApiUrl}
          adapter={HydraAdapter}
          fields={customerAssignmentFields}
          selectionMode="multiple"
          allowAdd={false}
          allowEdit={false}
          allowDelete={false}
          allowExport={false}
          toolbarVisible={false}
          filterRow
          onSelectionChanged={(event: DataGridSelectionChangedEvent) => {
            setSelectedCustomerIds(
              (event.selectedRowsData ?? [])
                .map((row) => row.id)
                .filter(
                  (id): id is string | number => typeof id === 'string' || typeof id === 'number',
                ),
            );
          }}
        />
      </DialogView>
    </>
  );
};
