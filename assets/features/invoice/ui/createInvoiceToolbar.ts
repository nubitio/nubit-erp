type ToolbarAction = {
  text: string;
  type: string;
  onClick: () => void;
};

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
}: CreateInvoiceToolbarParams): ToolbarAction[] => [
  { text: 'Enviar a SUNAT', type: 'info', onClick: onSendSunat },
  { text: 'Enviar por correo', type: 'success', onClick: onSendEmail },
  { text: 'Ver', type: 'primary', onClick: onView },
  { text: 'Consulta CDR', type: 'warning', onClick: onConsultCdr },
  { text: 'Consulta CPE', type: 'success', onClick: onConsultCpe },
];
