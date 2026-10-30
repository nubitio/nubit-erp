/* eslint-disable react-hooks/refs -- CRUD toolbar action handlers intentionally read Grid/Form refs inside callbacks, not during render. */
import { useMemo, useRef } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import {
  SchemaCrudPage,
  defineResource,
  type DataRecord,
  type FormHandle,
  type GridHandle,
} from '@nubitio/crud';
import { CreditNoteDetailFields, CreditNoteFields } from './CreditNoteFields';
import toast from '@shared/lib/SharedUtils';
import { useEvents } from '@nubitio/core';
import type { CreditNoteRow } from './lib/creditNoteActions';
import {
  runOpenCreditNoteReceiptAction,
  runSendCreditNoteToSunatAction,
  runConsultCreditNoteCdrAction,
  runConsultCreditNoteCpeAction,
} from './lib/creditNoteActions';
import { useCreditNoteRelatedDocumentObserver, useCreditNoteSelectionState } from './model';

const API_URL = API + 'tax-documents';
const fields = CreditNoteFields();

const CREDIT_NOTE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['issueDate', 'customer', 'series', 'number', 'sunatStatus'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'issueDate',
      'customer',
      'documentType',
      'series',
      'number',
      'reasonCode',
      'affectedDocument',
      'sunatStatus',
      'sunatMessage',
    ],
  },
];

export const CreditNotePage = () => {
  const [on] = useEvents();
  const gridRef = useRef<GridHandle | null>(null);
  const formRef = useRef<FormHandle | null>(null);
  const { editDisabled, deleteDisabled, handleSelectionChanged } = useCreditNoteSelectionState();

  useCreditNoteRelatedDocumentObserver({
    on,
    apiUrl: API_URL,
    formRef,
  });

  const actionDeps = useMemo(
    () => ({
      getSelectedRow: () => gridRef.current?.getSelectedRow() as CreditNoteRow | undefined,
      showLoading: (message?: string) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') =>
        toast(message, type),
    }),
    [],
  );

  const resource = useMemo(
    () =>
      defineResource(API_URL + '?type=note', {
        id: 'creditNote',
        title: 'Notas de crédito',
        gridAppearance: 'page',
        filterRow: false,
        filterToolbarFields: ['issueDate', 'customer', 'sunatStatus'],
        fields,
        defaultPreset: 'basic',
        columnPresets: CREDIT_NOTE_COLUMN_PRESETS,
        viewMode: 'page',
        routing: { routeParam: 'id' },
        formLayout: {
          type: 'tabs',
          tabs: [
            {
              label: 'Documento',
              icon: 'ph-receipt',
              fields: ['relatedTaxDocument', 'issueDate'],
            },
            {
              label: 'Motivo',
              icon: 'ph-note',
              fields: ['reasonCode', 'reasonDescription'],
            },
          ],
        },
        emptyState: {
          title: 'Sin notas de crédito',
          description: 'Las notas de crédito emitidas aparecerán aquí.',
          icon: 'file-text',
        },
        gridDetail: {
          url: API_URL + '/{id}/lines',
          fields: (parentRow: DataRecord) =>
            CreditNoteDetailFields((parentRow?.currency as string) ?? 'PEN'),
        },
        formDetail: {
          propertyName: 'lines',
          allowAdding: false,
          allowDeleting: true,
          allowUpdating: true,
          required: true,
        },
        permissions: { canAdd: true, canEdit: true, canDelete: true },
        toolbar: {
          selection: [
            {
              text: 'Ver',
              type: 'primary',
              group: 'view',
              groupLabel: 'Consulta',
              onClick: () => runOpenCreditNoteReceiptAction(actionDeps),
            },
            {
              text: 'Consulta CDR',
              type: 'warning',
              group: 'view',
              groupLabel: 'Consulta',
              onClick: () => runConsultCreditNoteCdrAction(actionDeps),
            },
            {
              text: 'Consulta CPE',
              type: 'success',
              group: 'view',
              groupLabel: 'Consulta',
              onClick: () => runConsultCreditNoteCpeAction(actionDeps),
            },
            {
              text: 'Enviar a SUNAT',
              type: 'info',
              group: 'delivery',
              groupLabel: 'Envío',
              onClick: () => runSendCreditNoteToSunatAction(actionDeps),
            },
            {
              text: 'Enviar por correo',
              type: 'success',
              group: 'delivery',
              groupLabel: 'Envío',
              onClick: () => toast('Funcionalidad no implementada', 'warning'),
            },
          ],
        },
      }),
    [actionDeps],
  );

  return (
    <SchemaCrudPage
      resource={resource}
      formRef={formRef}
      gridRef={gridRef}
      editDisabled={editDisabled}
      deleteDisabled={deleteDisabled}
      onSelectionChanged={handleSelectionChanged}
    />
  );
};
