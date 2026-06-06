import './SalePage.scss';
import {
  DataGridView,
  DrawerView,
  FormView,
  FORM_EVENTS,
  type DetailSummaryOptions,
  type FormHandle,
  type GridHandle,
} from '@nubit/crud';
import { saleEndpoints } from './api/saleEndpoints';
import { SaleDetailFields, SaleFields, SaleFieldStateRef, setTotal } from './SaleFields';
import { useSessionInfo } from '@app/contexts/useSessionInfo';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  DiscountDialog,
  DiscountDialogApplyPayload,
  DiscountDialogEvent,
} from '@shared/ui/discount-dialog/DiscountDialog';
import { applySaleAutoInvoiceOnAdd, saleEvents, useSalePageObservers } from './model';
import { Setting, settings } from '@app/Settings';
import { cloneSaleToForm, createInvoicePrinter, runMakeInvoiceAction, runReplaceRejectedAction, runVoidSaleAction } from './lib';
import { createSaleToolbar, useSalePrintPreview } from './ui';
import { CustomerPage, CustomerFields } from '@features/customer';
import { SalePrintPreviewPopup } from './SalePrintPreviewPopup';
import { SaleTaxDocumentsDialog } from './SaleTaxDocumentsDialog';
import { useEvents } from '@nubit/core';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import type { DataRecord } from '@nubit/crud';

export const SalePage = () => {
  const [on, emit] = useEvents();
  const gridRef = useRef<GridHandle>(null);
  const formRef = useRef<FormHandle>(null);
  const customerFormRef = useRef<FormHandle>(null);
  const invoicePrinterRef = useRef(createInvoicePrinter());
  // Shared mutable state threaded between SaleFields (header) and SaleDetailFields (lines).
  // Holds the currently selected warehouse ID (for stock checks) and fetched price list items.
  const saleFieldStateRef = useRef<SaleFieldStateRef>({ priceList: [], warehouseId: '' });
  const salePrintPreview = useSalePrintPreview();
  const [printEnabled, setPrintEnabled] = useState<boolean>(false);
  const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);
  const [replaceEnabled, setReplaceEnabled] = useState<boolean>(false);
  const [documentsEnabled, setDocumentsEnabled] = useState<boolean>(false);
  const [documentsDialogSaleId, setDocumentsDialogSaleId] = useState<string | null>(null);
  const { currentBranchId, currentUserId } = useSessionInfo();

  const formHandleProxy = useMemo<FormHandle>(() => ({
    getFormData: () => formRef.current?.getFormData(),
    getFieldValue: (name) => formRef.current?.getFieldValue(name),
    setFieldValue: (name, value) => formRef.current?.setFieldValue(name, value),
    getDetailData: () => formRef.current?.getDetailData(),
    setDetailData: (data) => formRef.current?.setDetailData(data),
    saveDetailData: () => formRef.current?.saveDetailData(),
    reloadDetailData: () => formRef.current?.reloadDetailData(),
    setValues: (data) => formRef.current?.setValues(data),
    save: () => formRef.current?.save(),
    deleteRow: (row) => formRef.current?.deleteRow(row),
    setReadonly: (field, value) => formRef.current?.setReadonly(field, value),
    setDisabled: (field, value) => formRef.current?.setDisabled(field, value),
    enableValidation: (field) => formRef.current?.enableValidation(field),
    disableValidation: (field) => formRef.current?.disableValidation(field),
    setError: (field, message) => formRef.current?.setError(field, message),
    showField: (field) => formRef.current?.showField(field),
    hideField: (field) => formRef.current?.hideField(field),
    validate: () => formRef.current?.validate() ?? false,
    setIsEdit: (value) => formRef.current?.setIsEdit(value),
  }), []);

  const customerFields = useMemo(() => CustomerFields(customerFormRef), []);

  const container = 'wrapper';
  const fields = useMemo(
    () =>
      SaleFields(formHandleProxy, {
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
          // discountPercent is already 0–100 (both from the form field and from SaleFields callback).
          const currentDiscountPercent = Number(
            formRef.current?.getFieldValue('discountPercent') ?? discountPercent,
          );
          emit(DiscountDialogEvent.OPEN, {
            total: detailTotal > 0 ? detailTotal : total,
            discountPercent: currentDiscountPercent,
          });
        },
        stateRef: saleFieldStateRef,
      }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [formHandleProxy],
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
          // discountPercent from the row is already 0–100 — pass directly.
          discountPercent: discountPercent,
          context: { rowIndex },
        });
      },
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const detailFields = useMemo(
    () => SaleDetailFields(formHandleProxy, 'PEN', { ...itemDiscountCallbacks, stateRef: saleFieldStateRef }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [formHandleProxy, itemDiscountCallbacks],
  );
  const detailSummary = useMemo<DetailSummaryOptions>(
    () => ({
      sticky: true,
      items: [
        {
          column: 'netAmount',
          label: 'Total',
          summaryType: 'sum',
          valueFormat: (value) => NumberUtils.formatCurrency(Number(value ?? 0), 'PEN'),
        },
      ],
    }),
    [],
  );
  const gridDetailFields = useMemo(
    () => (parentRow: DataRecord) =>
      SaleDetailFields(
        formRef.current,
        (parentRow?.currency as string) ?? 'PEN',
        { stateRef: saleFieldStateRef },
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
        // discountPercent from the dialog is already 0–100 — store directly.
        const { rowIndex } = context as { rowIndex: number };
        const rows = formRef.current?.getDetailData() ?? [];
        const row = rows[rowIndex] as DataRecord | undefined;
        if (row) {
          row.discountPercent = Number(discountPercent.toFixed(2));
          row.discountAmount = discountAmount;
          const qty = Number(row.qty ?? 0);
          const unitPrice = Number(row.unitPrice ?? 0);
          row.netAmount = Number((qty * unitPrice - discountAmount).toFixed(2));
          formRef.current?.setDetailData([...rows]);
          setTotal(formRef.current);
        }
      } else {
        // Global discount: apply to the sale header.
        // discountPercent from the dialog is already 0–100 — store directly.
        formRef.current?.setFieldValue('discountPercent', Number(discountPercent.toFixed(2)));
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
      // Hide exchange rate by default when currency is PEN (the default).
      // The currency field's onChange will show it again if the user switches to a foreign currency.
      const defaultCurrency = settings.getCached(Setting.DEFAULT_CURRENCY)?.value ?? 'PEN';
      const currency = (formRef.current?.getFieldValue('currency') as string | undefined) ?? defaultCurrency;
      if (currency === 'PEN' || !currency) {
        formRef.current?.hideField('exchangeRate');
      }
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

  const rowActions = (row: DataRecord) => {
    const saleId = row.id != null ? String(row.id) : undefined;
    if (!saleId) return [];

    return [
      {
        text: 'Vista previa',
        icon: 'ph-eye',
        onClick: () => salePrintPreview.openPreview(saleId),
      },
      {
        text: 'Imprimir',
        icon: 'ph-printer',
        onClick: () => print(saleId),
      },
      {
        text: 'Duplicar',
        icon: 'ph-copy',
        onClick: () => cloneSaleToForm({
          saleId,
          emit,
          addEventName: saleEvents.ADD,
          loadingEventName: FORM_EVENTS.LOADING,
        }),
      },
      {
        text: 'Anular',
        icon: 'ph-x-circle',
        type: 'danger',
        onClick: () => voidSale(saleId),
      },
    ];
  };

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
        rowActions={rowActions}
        detailUrl={saleEndpoints.items('{id}')}
        detailFields={gridDetailFields}
        events={saleEvents}
        sort={[{ selector: 'createdAt', desc: true }]}
      />
      <DrawerView drawerWidth={1200} events={saleEvents}>
        <FormView
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          detailSummary={detailSummary}
          detailPropertyName="writeItems"
          events={saleEvents}
        />
      </DrawerView>
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
