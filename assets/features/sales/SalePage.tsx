import './SalePage.scss';
import {
  DataGridView,
  DialogView,
  FormView,
  FORM_EVENTS,
  type FormHandle,
  type GridHandle,
} from '@nubit/crud';
import { saleEndpoints } from './api/saleEndpoints';
import { SaleDetailFields, SaleFields, setTotal } from './SaleFields';
import { useSessionInfo } from '@app/contexts/useSessionInfo';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  DiscountDialog,
  DiscountDialogApplyPayload,
  DiscountDialogEvent,
} from '@shared/ui/discount-dialog/DiscountDialog';
import {
  applySaleAutoInvoiceOnAdd,
  cloneSaleToForm,
  createInvoicePrinter,
  createSaleToolbar,
  runMakeInvoiceAction,
  runReplaceRejectedAction,
  runVoidSaleAction,
  saleEvents,
  useSalePrintPreview,
  useSalePageObservers,
} from '@features/sale';
import { CustomerPage, CustomerFields } from '@features/customer';
import { SalePrintPreviewPopup } from './SalePrintPreviewPopup';
import { SaleTaxDocumentsDialog } from './SaleTaxDocumentsDialog';
import { useEvents } from '@nubit/core';
import toast from '@shared/lib/SharedUtils';
import type { DataRecord } from '@nubit/crud';

export const SalePage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<GridHandle>(null);
  const formRef = useRef<FormHandle>(null);
  const customerFormRef = useRef<FormHandle>(null);
  const invoicePrinterRef = useRef(createInvoicePrinter());
  const salePrintPreview = useSalePrintPreview();
  const [printEnabled, setPrintEnabled] = useState<boolean>(false);
  const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);
  const [replaceEnabled, setReplaceEnabled] = useState<boolean>(false);
  const [documentsEnabled, setDocumentsEnabled] = useState<boolean>(false);
  const [documentsDialogSaleId, setDocumentsDialogSaleId] = useState<string | null>(null);
  const { currentBranchId, currentUserId } = useSessionInfo();

  const customerFields = useMemo(() => CustomerFields(customerFormRef), []);

  const container = 'wrapper';
  const fields = useMemo(
    () =>
      SaleFields(formRef.current, {
        branchId: currentBranchId,
        userId: currentUserId ?? undefined,
        onOpenAddCustomer: () => emit(CustomerPage.EVENTS.ADD),
        onOpenDiscount: async ({ total, discountPercent }) => {
          await formRef.current?.saveDetailData();
          const rows = formRef.current?.getDetailData() ?? [];
          const detailTotal = rows.reduce(
            (sum, row) => sum + Number(row.netAmount ?? 0),
            0,
          );
          const currentDiscountPercent = Number(
            formRef.current?.getFieldValue('discountPercent') ?? discountPercent * 100,
          );
          emit(DiscountDialogEvent.OPEN, {
            total: detailTotal > 0 ? detailTotal : total,
            discountPercent: currentDiscountPercent / 100,
          });
        },
      }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  /** Callbacks for item-level discount icon in the detail grid. */
  const itemDiscountCallbacks = useMemo(
    () => ({
      onOpenItemDiscount: ({
        rowIndex,
        baseValue,
        discountPercent,
        discountAmount: _discountAmount,
      }: {
        rowIndex: number;
        baseValue: number;
        discountPercent: number;
        discountAmount: number;
      }) => {
        emit(DiscountDialogEvent.OPEN, {
          title: 'Descuento por ítem',
          total: baseValue,
          // DiscountDialog works with fractions [0,1]; discountPercent in the row is 0–100.
          discountPercent: discountPercent / 100,
          context: { rowIndex },
        });
      },
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const detailFields = useMemo(
    () => SaleDetailFields(formRef.current, 'PEN', itemDiscountCallbacks),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );
  const gridDetailFields = useMemo(
    () => (parentRow: DataRecord) =>
      SaleDetailFields(
        formRef.current,
        (parentRow?.currency as string) ?? 'PEN',
      ),
    [],
  );

  const API_URL = saleEndpoints.list();

  const setupPrinter = () => {
    invoicePrinterRef.current.initialize().catch((_err: unknown) => {
      // Initialization is best-effort (silent reconnect only). Errors are ignored.
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
    setReplaceEnabled,
    setDocumentsEnabled,
    onDiscountApply: (payload: DiscountDialogApplyPayload) => {
      const { discountPercent, discountAmount, context } = payload;

      if (context != null && typeof context === 'object' && 'rowIndex' in context) {
        // Item-level discount: update the specific detail row.
        const { rowIndex } = context as { rowIndex: number };
        const rows = formRef.current?.getDetailData() ?? [];
        const row = rows[rowIndex] as DataRecord | undefined;
        if (row) {
          row.discountPercent = Number((discountPercent * 100).toFixed(2));
          row.discountAmount = discountAmount;
          const qty = Number(row.qty ?? 0);
          const unitPrice = Number(row.unitPrice ?? 0);
          row.netAmount = Number((qty * unitPrice - discountAmount).toFixed(2));
          formRef.current?.setDetailData([...rows]);
          setTotal(formRef.current);
        }
      } else {
        // Global discount: apply to the sale header.
        const discount = Number(discountPercent * 100).toFixed(2);
        formRef.current?.setFieldValue('discountPercent', Number(discount));
        setTotal(formRef.current);
      }
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

  const replaceRejected = (saleId: string) => {
    runReplaceRejectedAction(saleId, {
      showLoading: (message) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message, type) => toast(message, type),
    });
  };

  const print = (saleId: string) => {
    invoicePrinterRef.current.printSaleInvoice(saleId).catch((err: unknown) => {
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
    onReplaceRejected: replaceRejected,
    onViewDocuments: (saleId) => setDocumentsDialogSaleId(saleId),
    printEnabled,
    cloneEnabled,
    replaceEnabled,
    documentsEnabled,
  });

  return (
    <div id={container}>
      <DataGridView
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
      <DialogView width={1200} events={saleEvents}>
        <FormView
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          detailPropertyName="writeItems"
          events={saleEvents}
          useIRI={true}
        />
      </DialogView>
      <DiscountDialog />
      <DialogView events={CustomerPage.EVENTS}>
        <FormView
          ref={customerFormRef}
          url={CustomerPage.API_URL}
          fields={customerFields}
          events={CustomerPage.EVENTS}
        />
      </DialogView>
      <SalePrintPreviewPopup
        saleId={salePrintPreview.selectedSaleIdRef.current}
        visible={salePrintPreview.isPreviewVisible}
        onPositive={salePrintPreview.confirmPreview}
        onNegative={salePrintPreview.closePreview}
      />
      <SaleTaxDocumentsDialog
        saleId={documentsDialogSaleId}
        visible={documentsDialogSaleId !== null}
        onClose={() => setDocumentsDialogSaleId(null)}
      />
    </div>
  );
};
