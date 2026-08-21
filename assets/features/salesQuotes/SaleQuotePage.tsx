import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  DataGridView,
  FormView,
  HydraAdapter,
  PageView,
  type DataRecord,
  type FormHandle,
  type GridHandle,
} from '@nubitio/crud';
import { useEvents } from '@nubitio/core';
import toast from '@shared/lib/SharedUtils';
import { saleQuoteEndpoints } from './api/saleQuoteEndpoints';
import { SaleQuoteFields, SaleQuoteDetailFields } from './SaleQuoteFields';
import { SaleQuoteConvertDialog } from './SaleQuoteConvertDialog';
import saleQuoteService from './services/saleQuoteService';
import { saleQuoteEvents } from './model/constants';

const SALE_QUOTE_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'Datos principales',
      fields: ['customer', 'branch', 'warehouse', 'currency', 'validUntil'],
    },
    {
      label: 'Notas',
      fields: ['note'],
    },
  ],
};

interface SaleQuoteRow extends DataRecord {
  id: string;
  status: 'draft' | 'sent' | 'accepted' | 'expired' | 'converted';
  customer?: { businessName?: string } | null;
  grandTotal?: string;
}

export const SaleQuotePage = () => {
  const navigate = useNavigate();
  const gridRef = useRef<GridHandle>(null);
  const formRef = useRef<FormHandle>(null);
  const [quoteFormOpen, setQuoteFormOpen] = useState(false);
  const [convertQuote, setConvertQuote] = useState<SaleQuoteRow | null>(null);
  const [on] = useEvents();

  const fields = useMemo(() => SaleQuoteFields(), []);
  const detailFields = useMemo(() => SaleQuoteDetailFields(), []);

  useEffect(() => {
    const subscriptions = [
      on(saleQuoteEvents.ADD, () => setQuoteFormOpen(true)),
      on(saleQuoteEvents.EDIT, () => setQuoteFormOpen(true)),
      on(saleQuoteEvents.CANCEL, () => setQuoteFormOpen(false)),
      on(saleQuoteEvents.SUCCESS, () => setQuoteFormOpen(false)),
    ];

    return () => subscriptions.forEach((subscription) => subscription.unsubscribe());
  }, [on]);

  const API_URL = saleQuoteEndpoints.list();

  const sendQuote = (quoteId: string) => {
    saleQuoteService()
      .send(quoteId)
      .then(() => {
        toast('Cotización enviada', 'success');
        gridRef.current?.refresh();
      })
      .catch(() => toast('No se pudo enviar la cotización', 'error'));
  };

  const rowActions = (row: DataRecord) => {
    const quote = row as SaleQuoteRow;
    const actions = [];

    if (quote.status === 'draft') {
      actions.push({
        text: 'Enviar',
        icon: 'ph-paper-plane-tilt',
        onClick: () => sendQuote(quote.id),
      });
    }

    if (['draft', 'sent', 'accepted'].includes(quote.status)) {
      actions.push({
        text: 'Convertir a venta',
        icon: 'ph-arrows-left-right',
        type: 'success' as const,
        onClick: () => setConvertQuote(quote),
      });
    }

    return actions;
  };

  return (
    <div id="wrapper" className={quoteFormOpen ? 'wrapper--with-page' : undefined}>
      {!quoteFormOpen && (
        <DataGridView
          id="sale-quotes"
          ref={gridRef}
          title="Cotizaciones"
          url={API_URL}
          fields={fields}
          allowAdd
          adapter={HydraAdapter}
          rowActions={rowActions}
          events={saleQuoteEvents}
          sort={[{ selector: 'issuedAt', desc: true }]}
          filterRow={false}
          emptyState={{
            title: 'Sin cotizaciones',
            description: 'Crea una cotización con el botón Nuevo y envíala al cliente.',
            icon: 'file-text',
          }}
        />
      )}
      <PageView events={saleQuoteEvents} onClose={() => setQuoteFormOpen(false)}>
        <FormView
          ref={formRef}
          url={API_URL}
          fields={fields}
          detailFields={detailFields}
          detailPropertyName="writeItems"
          adapter={HydraAdapter}
          formLayout={SALE_QUOTE_FORM_LAYOUT}
          events={saleQuoteEvents}
          presentationContext={{
            presentationMode: 'page',
            hasMasterDetail: true,
            hasTabs: false,
          }}
        />
      </PageView>
      <SaleQuoteConvertDialog
        quoteId={convertQuote?.id ?? null}
        customerLabel={convertQuote?.customer?.businessName}
        grandTotal={convertQuote?.grandTotal}
        onClose={() => setConvertQuote(null)}
        onConverted={(saleId) => {
          setConvertQuote(null);
          gridRef.current?.refresh();
          navigate(`/sales?highlight=${saleId}`);
        }}
      />
    </div>
  );
};

SaleQuotePage.EVENTS = saleQuoteEvents;
SaleQuotePage.API_URL = saleQuoteEndpoints.list();
