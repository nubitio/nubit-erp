/* eslint-disable react-hooks/refs -- CRUD action adapters intentionally read Grid/Form refs inside callbacks, not during render. */
import './SalePage.scss';
import { useScreenSize } from '@nubitio/admin';
import { CollapsibleSection } from '@nubitio/ui';
import { hasPermission } from '@shared/permissions';
import './detail/SaleDetailPage.scss';
import { useNavigate } from 'react-router-dom';
import { SaleCardContent } from './detail/SaleCardContent';
import { useQueryClient } from '@tanstack/react-query';
import { saleFilterPresets } from './model/saleFilterPresets';
import { MonthlySalesKpis } from './MonthlySalesKpis';
import {
  ColumnChooserButton,
  DataGridView,
  DrawerView,
  FormView,
  FORM_EVENTS,
  PageView,
  useColumnChooser,
  type DetailSummaryOptions,
  type FormHandle,
  type GridHandle,
  type ResourceConfig,
} from '@nubitio/crud';
import { useCoreTranslation } from '@nubitio/core';
import { saleEndpoints } from './api/saleEndpoints';
import { SaleDetailFields, SaleFields, SaleFieldStateRef, setTotal } from './SaleFields';
import { useSessionInfo } from '@shared/sessionInfo';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  DiscountDialog,
  DiscountDialogApplyPayload,
  DiscountDialogEvent,
} from '@shared/ui/discount-dialog/DiscountDialog';
import { applySaleAutoInvoiceOnAdd, saleEvents, useSalePageObservers } from './model';
import { Setting, settings } from '@shared/settings';
import {
  cloneSaleToForm,
  createInvoicePrinter,
  runMakeInvoiceAction,
  runReplaceRejectedAction,
  runVoidSaleAction,
} from './lib';
import { createSaleToolbar, useSalePrintPreview } from './ui';
import { requestAssistantHelp } from '@features/assistant';
import { useFeature } from '@shared/hooks';
import { CustomerPage, CustomerFields, customerFormLayout } from '@features/customer';
import { SalePrintPreviewPopup } from './SalePrintPreviewPopup';
import { SaleTaxDocumentsDialog } from './SaleTaxDocumentsDialog';
import { useEvents } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import NumberUtils from '@shared/lib/NumberUtils';
import type { DataRecord } from '@nubitio/crud';
import { mapPosCustomer } from '@features/pos/lib/mapPosCustomer';

const SALE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: [
      'issuedDate',
      'invoiceFullNumber',
      'customer',
      'paymentMethod',
      'grandTotal',
      'paymentState',
      'sunatState',
    ],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'issuedDate',
      'customer',
      'paymentMethod',
      'invoiceFullNumber',
      'discountPercent',
      'grandTotal',
      'paymentState',
      'sunatState',
    ],
  },
  {
    key: 'receivable',
    label: 'Por cobrar',
    columns: [
      'issuedDate',
      'customer',
      'dueDate',
      'grandTotal',
      'balanceDue',
      'paymentState',
      'sunatState',
    ],
  },
];

const SALE_PRESET_RESOURCE: ResourceConfig = {
  id: 'sales',
  title: 'Ventas',
  apiUrl: '',
  defaultPreset: 'basic',
  columnPresets: SALE_COLUMN_PRESETS,
};

const SALE_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'Datos principales',
      fields: ['emitInvoice', 'issuedDate', 'warehouse', 'customer', 'note'],
    },
    {
      label: 'Pagos',
      fields: ['salePayments'],
    },
    {
      label: 'Comercial',
      collapsible: true,
      defaultCollapsed: true,
      fields: ['currency', 'exchangeRate', 'price_list', 'discountPercent'],
    },
  ],
};

export const SalePage = () => {
  const { t } = useCoreTranslation();
  const navigate = useNavigate();
  const { isXSmall } = useScreenSize();
  const [summaryExpanded, setSummaryExpanded] = useState(false);
  const queryClient = useQueryClient();
  const [on, emit] = useEvents();
  const gridRef = useRef<GridHandle>(null);
  const formRef = useRef<FormHandle>(null);
  const customerFormRef = useRef<FormHandle>(null);
  const invoicePrinterRef = useRef(createInvoicePrinter());
  const saleFieldStateRef = useRef<SaleFieldStateRef>({ priceList: [], warehouseId: '' });
  const salePrintPreview = useSalePrintPreview();
  const [printEnabled, setPrintEnabled] = useState<boolean>(false);
  const [cloneEnabled, setCloneEnabled] = useState<boolean>(false);
  const [replaceEnabled, setReplaceEnabled] = useState<boolean>(false);
  const [documentsEnabled, setDocumentsEnabled] = useState<boolean>(false);
  const [documentsDialogSaleId, setDocumentsDialogSaleId] = useState<string | null>(null);
  const [saleFormOpen, setSaleFormOpen] = useState(false);
  const { currentBranchId, currentUserId } = useSessionInfo();
  const assistantEnabled = useFeature('ai_assistant');

  const formHandleProxy = useMemo<FormHandle>(
    () => ({
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
    }),
    [],
  );

  const customerFields = useMemo(() => CustomerFields(customerFormRef), []);

  useEffect(() => {
    const subscriptions = [
      on(saleEvents.ADD, () => setSaleFormOpen(true)),
      on(saleEvents.EDIT, () => setSaleFormOpen(true)),
      on(saleEvents.CANCEL, () => setSaleFormOpen(false)),
      on(saleEvents.SUCCESS, () => setSaleFormOpen(false)),
    ];

    return () => subscriptions.forEach((subscription) => subscription.unsubscribe());
  }, [on]);

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
          const detailTotal = rows.reduce((sum, row) => sum + Number(row.netAmount ?? 0), 0);
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
  const columnChooserState = useColumnChooser(SALE_PRESET_RESOURCE, fields);

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
          discountPercent: discountPercent,
          context: { rowIndex },
        });
      },
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const detailFields = useMemo(
    () =>
      SaleDetailFields(formHandleProxy, 'PEN', {
        ...itemDiscountCallbacks,
        stateRef: saleFieldStateRef,
      }),
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
      SaleDetailFields(formRef.current, (parentRow?.currency as string) ?? 'PEN', {
        stateRef: saleFieldStateRef,
      }),
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
        formRef.current?.setFieldValue('discountPercent', Number(discountPercent.toFixed(2)));
        setTotal(formRef.current);
      }
    },
    onDetailRowsChanged: () => {
      setTotal(formRef.current);
    },
    onCustomerCreated: (payload) => {
      const { data } = payload as { data?: unknown };
      formRef.current?.setFieldValue('customer', data);
      formRef.current?.setFieldValue('customerDocument', mapPosCustomer(data)?.doc ?? '');
    },
    onSaleAdd: async () => {
      const defaultCurrency = settings.getCached(Setting.DEFAULT_CURRENCY)?.value ?? 'PEN';
      const currency =
        (formRef.current?.getFieldValue('currency') as string | undefined) ?? defaultCurrency;
      if (currency === 'PEN' || !currency) {
        formRef.current?.hideField('exchangeRate');
      }
      window.setTimeout(() => {
        const currentCurrency =
          (formRef.current?.getFieldValue('currency') as string | undefined) ?? defaultCurrency;
        if (currentCurrency === 'PEN' || !currentCurrency) {
          formRef.current?.hideField('exchangeRate');
        }
      });
      await applySaleAutoInvoiceOnAdd(formRef);
    },
  });

  useEffect(() => {
    setupPrinterRef.current();
  }, []);

  const refreshSales = () => {
    gridRef.current?.refresh();
    void queryClient.invalidateQueries({ queryKey: ['sales', 'monthly-summary'] });
  };

  const voidSale = async (saleId: string) => {
    await runVoidSaleAction(saleId, {
      showLoading: (message) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: refreshSales,
      notify: (message, type) => toast(message, type),
    });
  };

  const makeInvoice = (saleId: string) => {
    runMakeInvoiceAction(saleId, {
      showLoading: (message) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: refreshSales,
      notify: (message, type) => toast(message, type),
      printSale: print,
    });
  };

  const replaceRejected = (saleId: string) => {
    runReplaceRejectedAction(saleId, {
      showLoading: (message) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: refreshSales,
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
    onExplainWithAi: assistantEnabled
      ? (saleId) =>
          requestAssistantHelp(
            `Explica el estado SUNAT de la venta ${saleId}. Si el comprobante fue rechazado, propone reemplazarlo. Si fue aceptado y hay que anularlo o devolverlo, propone una nota de crédito. Nunca sugieras saltarse SUNAT.`,
          )
      : undefined,
    onViewDocuments: (saleId) => setDocumentsDialogSaleId(saleId),
    printEnabled,
    cloneEnabled,
    replaceEnabled,
    documentsEnabled,
    canCreate: hasPermission('sales.create'),
  });

  const rowActions = (row: DataRecord) => {
    const saleId = row.id != null ? String(row.id) : undefined;
    if (!saleId) return [];

    const taxDocumentId =
      typeof row.taxDocumentId === 'string' && row.taxDocumentId !== ''
        ? row.taxDocumentId
        : saleId;

    return [
      {
        text: 'Ver detalle',
        icon: 'ph-eye',
        onClick: () => navigate(`/billing/sales/${encodeURIComponent(saleId)}`),
      },
      {
        text: 'Vista previa',
        icon: 'ph-eye',
        onClick: () => salePrintPreview.openPreview(taxDocumentId),
      },
      {
        text: 'Imprimir',
        icon: 'ph-printer',
        onClick: () => print(saleId),
      },
      {
        text: 'Duplicar',
        visible: hasPermission('sales.create'),
        icon: 'ph-copy',
        onClick: () =>
          cloneSaleToForm({
            saleId,
            emit,
            addEventName: saleEvents.ADD,
            loadingEventName: FORM_EVENTS.LOADING,
          }),
      },
      {
        text: 'Anular',
        visible: hasPermission('sales.void') && row.status !== 'VOIDED' && !row.hasInvoice,
        icon: 'ph-x-circle',
        type: 'danger',
        onClick: () => voidSale(saleId),
      },
    ];
  };

  const renderColumnChooser = useCallback(
    () => (
      <ColumnChooserButton
        state={columnChooserState}
        triggerLabel={t('crudPage.columnChooserLabel')}
        selectAllLabel={t('crudPage.columnChooserSelectAll')}
        resetLabel={t('crudPage.columnChooserReset')}
      />
    ),
    [columnChooserState, t],
  );

  return (
    <div id={container} className={`sale-page${saleFormOpen ? ' wrapper--with-page' : ''}`}>
      {!saleFormOpen && (
        <>
          {isXSmall ? (
            <div className="sale-page__summary-toggle">
              <CollapsibleSection
                label="Resumen mensual"
                open={summaryExpanded}
                onToggle={() => setSummaryExpanded((open) => !open)}
              >
                <MonthlySalesKpis branchId={currentBranchId} />
              </CollapsibleSection>
            </div>
          ) : (
            <MonthlySalesKpis branchId={currentBranchId} />
          )}
          <DataGridView
            id={'sales'}
            ref={gridRef}
            title={'Ventas'}
            appearance="page"
            cardView={{
              defaultView: 'table',
              renderContent: (context) => <SaleCardContent {...context} />,
            }}
            allowView
            onView={(row) => navigate(`/billing/sales/${encodeURIComponent(String(row.id))}`)}
            filterRow={false}
            filterToolbarFields={['issuedDate', 'paymentState', 'sunatState', 'paymentMethod']}
            filterPresets={saleFilterPresets()}
            url={API_URL}
            fields={fields}
            allowAdd={hasPermission('sales.create')}
            toolbar={toolbar}
            rowActions={rowActions}
            detailUrl={saleEndpoints.items('{id}')}
            detailFields={gridDetailFields}
            events={saleEvents}
            sort={[{ selector: 'createdAt', desc: true }]}
            visibleColumns={columnChooserState.visibleColumns}
            beforeToolbar={renderColumnChooser}
            emptyState={{
              title: 'Sin ventas registradas',
              description: 'Crea una venta con el botón Nuevo.',
              icon: 'receipt',
            }}
          />
        </>
      )}
      <PageView events={saleEvents} onClose={() => setSaleFormOpen(false)}>
        <FormView
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          detailSummary={detailSummary}
          detailPropertyName="writeItems"
          formLayout={SALE_FORM_LAYOUT}
          events={saleEvents}
          presentationContext={{
            presentationMode: 'page',
            hasMasterDetail: true,
            hasTabs: false,
          }}
        />
      </PageView>
      <DiscountDialog />
      <DrawerView drawerSize="md" events={CustomerPage.EVENTS}>
        <FormView
          ref={customerFormRef}
          url={CustomerPage.API_URL}
          fields={customerFields}
          formLayout={customerFormLayout}
          events={CustomerPage.EVENTS}
        />
      </DrawerView>
      <SalePrintPreviewPopup
        saleId={salePrintPreview.selectedSaleIdRef.current ?? ''}
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
