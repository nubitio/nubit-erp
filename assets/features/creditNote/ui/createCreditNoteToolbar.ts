type ToolbarAction = {
  text: string;
  type: string;
  onClick: () => void;
};

type CreateCreditNoteToolbarParams = {
  onSendSunat: () => void;
  onSendEmail: () => void;
  onView: () => void;
};

export const createCreditNoteToolbar = ({
  onSendSunat,
  onSendEmail,
  onView,
}: CreateCreditNoteToolbarParams): ToolbarAction[] => [
  { text: 'Enviar a SUNAT', type: 'info', onClick: onSendSunat },
  { text: 'Enviar por correo', type: 'success', onClick: onSendEmail },
  { text: 'Ver', type: 'primary', onClick: onView },
];
