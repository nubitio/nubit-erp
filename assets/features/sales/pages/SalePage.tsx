import './SalePage.scss';
import { DxDataGrid, DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxForm, DxFormEvents, FORM_EVENTS } from '@core/form/DxForm';
import { DxDialog } from '@core/dialog/DxDialog';
import { saleEndpoints } from '../api/saleEndpoints';
import { SaleDetailFields, SaleFields, setTotal } from './SaleFields';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DiscountDialog, DiscountDialogEvent } from '@shared/ui/discount-dialog/DiscountDialog';
import {
  applySaleAutoInvoiceOnAdd,
  cloneSaleToForm,
  createInvoicePrinter,
  createSaleToolbar,
  runMakeInvoiceAction,
  runVoidSaleAction,
  saleEvents,
  useSalePrintPreview,
  useSalePageObservers,
} from '@features/sale';
import { CustomerPage, CustomerFields } from '@features/customer';
import { SalePrintPreviewPopup } from './SalePrintPreviewPopup';
import { useEvents } from '@core/event/EventHook';
import toast from '@shared/lib/SharedUtils';
import { prompt } from '@shared/ui/library/prompt-dialog/PromptDialog';

export const SalePage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<DxDataGridEvents>(null);
  const formRef = useRef<DxFormEvents>(null);
  const customerFormRef = useRef<DxFormEvents>(null);
  const qzInvoicePrinterRef = useRef(createInvoicePrinter());
  const salePrintPreview = useSalePrintPreview();
  const [printEnabled, setPrintEnabled] = useState<boolean>(false);
  const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);

  const customerFields = useMemo(() => CustomerFields(customerFormRef), []);

  const container = 'wrapper';
  const fields = useMemo(
    () =>
      SaleFields(formRef.current, {
        onOpenAddCustomer: () => emit(CustomerPage.EVENTS.ADD),
        onOpenDiscount: ({ total, discountPercent }) =>
          emit(DiscountDialogEvent.OPEN, { total, discountPercent }),
      }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );
  const detailFields = useMemo(() => SaleDetailFields(formRef.current), []);
  const gridDetailFields = useMemo(
    () => (parentRow: Record<string, unknown>) =>
      SaleDetailFields(formRef.current, (parentRow?.currency as string) ?? 'PEN'),
    [],
  );

  const API_URL = saleEndpoints.list();

  const setupPrinter = () => {
    qzInvoicePrinterRef.current.initialize().catch((_err: unknown) => {
      // QZ Tray not available — print features will be disabled silently
    });
  };
  const setupPrinterRef = useRef(setupPrinter);

  useSalePageObservers({
    on,
    discountApplyEvent: DiscountDialogEvent.APPLY,
    saleEvents,
    customerSuccessEvent: CustomerPage.EVENTS.SUCCESS,
    setPrintEnabled,
    setCloneEnabled,
    onDiscountApply: (discountPercent) => {
      const discount = Number(discountPercent * 100).toFixed(2);
      formRef.current?.setFieldValue('discountPercent', Number(discount));
    },
    onDetailRowsChanged: () => {
      setTotal(formRef.current);
    },
    onCustomerCreated: (payload) => {
      formRef.current?.setFieldValue('customer', payload.data);
    },
    onSaleAdd: async () => {
      await applySaleAutoInvoiceOnAdd(formRef);
    },
  });

  useEffect(() => {
    setupPrinterRef.current();
  }, []);

  const voidSale = async (saleId: string) => {
    await runVoidSaleAction(saleId, {
      askReason: () => prompt('Anular comprobante', 'Motivo de anulación'),
      showLoading: (message) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message, type) => toast(message, type),
    });
  };

  const makeInvoice = (saleId: string) => {
    runMakeInvoiceAction(saleId, {
      showLoading: (message) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message, type) => toast(message, type),
      printSale: print,
    });
  };

  const print = (saleId: string) => {
    qzInvoicePrinterRef.current.printSaleInvoice(saleId).catch((err: unknown) => {
      const message = err instanceof Error ? err.message : 'No se pudo imprimir el comprobante.';
      toast(message, 'error');
    });
  };

  const toolbar = createSaleToolbar({
    getSelectedSaleId: () => {
      const saleId = gridRef.current?.getSelectedRowKey();
      return saleId != null ? String(saleId) : undefined;
    },
    onPreview: salePrintPreview.openPreview,
    onPrint: print,
    onVoid: voidSale,
    onMakeInvoice: makeInvoice,
    onClone: (saleId) =>
      cloneSaleToForm({
        saleId,
        emit,
        addEventName: saleEvents.ADD,
        loadingEventName: FORM_EVENTS.LOADING,
      }),
    printEnabled,
    cloneEnabled,
  });

  return (
    <div id={container}>
      <DxDataGrid
        id={'sales'}
        ref={gridRef}
        title={'Ventas'}
        url={API_URL}
        fields={fields}
        allowAdd={true}
        toolbar={toolbar}
        detailUrl={saleEndpoints.items('{id}')}
        detailFields={gridDetailFields}
        events={saleEvents}
        sort={[{ selector: 'createdAt', desc: true }]}
      />
      <DxDialog width={1000} events={saleEvents}>
        <DxForm
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          detailPropertyName="writeItems"
          events={saleEvents}
          useIRI={true}
        />
      </DxDialog>
      <DiscountDialog />
      <DxDialog events={CustomerPage.EVENTS}>
        <DxForm
          ref={customerFormRef}
          url={CustomerPage.API_URL}
          fields={customerFields}
          events={CustomerPage.EVENTS}
        />
      </DxDialog>
      <SalePrintPreviewPopup
        saleId={salePrintPreview.selectedSaleIdRef.current}
        visible={salePrintPreview.isPreviewVisible}
        onPositive={salePrintPreview.confirmPreview}
        onNegative={salePrintPreview.closePreview}
      />
    </div>
  );
};
