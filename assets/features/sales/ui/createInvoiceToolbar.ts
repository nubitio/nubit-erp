import type { ResourceToolbarItems } from '@nubitio/crud';

type CreateInvoiceToolbarParams = {
  onSendSunat: () => void;
  onSendEmail: () => void;
  onView: () => void;
  onConsultCdr: () => void;
  onConsultCpe: () => void;
};

export const createInvoiceToolbar = ({
  onSendSunat,
  onSendEmail,
  onView,
  onConsultCdr,
  onConsultCpe,
}: CreateInvoiceToolbarParams): ResourceToolbarItems => ({
  selection: [
    { text: 'Ver', type: 'primary', group: 'view', groupLabel: 'Consulta', onClick: onView },
    { text: 'Consulta CDR', type: 'warning', group: 'view', groupLabel: 'Consulta', onClick: onConsultCdr },
    { text: 'Consulta CPE', type: 'success', group: 'view', groupLabel: 'Consulta', onClick: onConsultCpe },
    { text: 'Enviar a SUNAT', type: 'info', group: 'delivery', groupLabel: 'Envío', onClick: onSendSunat },
    { text: 'Enviar por correo', type: 'success', group: 'delivery', groupLabel: 'Envío', onClick: onSendEmail },
  ],
});
