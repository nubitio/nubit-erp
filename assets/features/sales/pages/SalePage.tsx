import './SalePage.scss';
import { DxDataGrid, DxDataGridEvents } from '@platform/datagrid/DxDataGrid';
import { DxForm, DxFormEvents, FORM_EVENTS } from '@platform/form/DxForm';
import { DxDialog } from '@platform/dialog/DxDialog';
import { Constants } from '@platform/common/Constants';
import { SaleDetailFields, SaleFields, setTotal } from './SaleFields';
import { useEffect, useRef, useState } from 'react';
import { DiscountDialog, DiscountDialogEvent } from '@widgets/discount-dialog/DiscountDialog';
import {
  applySaleAutoInvoiceOnAdd,
  cloneSaleToForm,
  createQzInvoicePrinter,
  createSaleToolbar,
  runMakeInvoiceAction,
  runVoidSaleAction,
  saleEvents,
  useSalePrintPreview,
  useSalePageObservers,
} from '@features/sale';
import { CustomerPage } from '@features/catalog/pages/CustomerPage';
import { CustomerFields } from '@features/catalog/pages/CustomerFields';
import { SalePrintPreviewPopup } from './SalePrintPreviewPopup';
import { useEvents } from '@platform/event/EventHook';
import toast from '@shared/lib/SharedUtils';
import { prompt } from '@widgets/library/prompt-dialog/PromptDialog';

export const SalePage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<DxDataGridEvents>(null);
  const formRef = useRef<DxFormEvents>(null);
  const customerFormRef = useRef<DxFormEvents>(null);
  const qzInvoicePrinterRef = useRef(createQzInvoicePrinter());
  const salePrintPreview = useSalePrintPreview();
  const [printEnabled, setPrintEnabled] = useState<boolean>(false);
  const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);

  const container = 'wrapper';
  const fields = SaleFields(formRef.current);
  const detailFields = SaleDetailFields(formRef.current);
  const gridDetailFields = (parentRow: any) =>
    SaleDetailFields(formRef.current, parentRow?.currency ?? 'PEN');

  const API_URL = Constants.API_BASE_URL + 'sales';

  const setupPrinter = () => {
    qzInvoicePrinterRef.current.initialize().catch((err: any) => {
      console.error(err);
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
    qzInvoicePrinterRef.current.printSaleInvoice(saleId).catch((err: any) => {
      console.error(err);
    });
  };

  const toolbar = createSaleToolbar({
    getSelectedSaleId: () => gridRef.current?.getSelectedRowKey(),
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
        detailUrl={API_URL + '/{id}/items'}
        detailFields={gridDetailFields}
        events={saleEvents}
        sort={[{ selector: 'createdAt', desc: true }]}
      />
      <DxDialog width={1000} height={760} events={saleEvents}>
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
          fields={CustomerFields(customerFormRef)}
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
