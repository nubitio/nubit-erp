import { useState } from 'react';
import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  imageField,
  FieldType,
  type DataRecord,
  type Field,
  type GridCellContext,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { dispatch, getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { Badge, ConfirmDialog } from '@nubitio/ui';
import { userInformation } from '@shared/sessionInfo';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { dateCell, moneyCell } from '@shared/ui/crudFormatters';
import { expenseReportApiUrl, expenseReportEvents, expenseReportFormLayout } from './model/constants';
import expenseReportService from './services/expenseReportService';

interface ExpenseReportRecord extends DataRecord {
  id: string;
  employee: unknown;
  category: unknown;
  branch: unknown;
  description: string;
  amount: string;
  currency: string;
  expenseDate: string;
  receiptMedia: unknown;
  status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'REIMBURSED';
  note: string | null;
}

function toPatch(field: Field): SmartCrudFieldPatch<ExpenseReportRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<ExpenseReportRecord>;
}

const STATUS_BADGE: Record<ExpenseReportRecord['status'], { variant: 'success' | 'warning' | 'secondary' | 'danger'; text: string }> = {
  PENDING: { variant: 'warning', text: 'Pendiente' },
  APPROVED: { variant: 'secondary', text: 'Aprobado' },
  REJECTED: { variant: 'danger', text: 'Rechazado' },
  REIMBURSED: { variant: 'success', text: 'Reembolsado' },
};

const statusPatch = toPatch(
  enumField([
    { value: 'PENDING', text: 'Pendiente' },
    { value: 'APPROVED', text: 'Aprobado' },
    { value: 'REJECTED', text: 'Rechazado' },
    { value: 'REIMBURSED', text: 'Reembolsado' },
  ])
    .name('status')
    .label('Estado')
    .formatter((cell: GridCellContext) => {
      const badge = STATUS_BADGE[cell.value as ExpenseReportRecord['status']];
      if (!badge) return null;

      return <Badge variant={badge.variant} outlined>{badge.text}</Badge>;
    })
    .build(),
);

const EXPENSE_REPORT_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['employee', 'category', 'expenseDate', 'amount', 'status'],
  },
];

const expenseReportResource = defineResource<ExpenseReportRecord>(expenseReportApiUrl, {
  id: 'expense-report',
  title: 'Gastos de personal',
  events: expenseReportEvents,
  defaultPreset: 'basic',
  columnPresets: EXPENSE_REPORT_COLUMN_PRESETS,
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  formLayout: expenseReportFormLayout,
  sort: [{ selector: 'expenseDate', desc: true }],
  emptyState: {
    title: 'Sin gastos de personal',
    description: 'Registra un viático o reembolso pendiente de aprobación.',
    icon: 'airplane-tilt',
  },
  fieldContract: defineFields<ExpenseReportRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'employee',
        patch: {
          order: 0,
          type: FieldType.ENTITY,
          url: API + 'users',
          valueField: '_iri',
          textField: 'username',
          layoutHint: 'lookup',
          label: 'Empleado',
          defaultValue: () => {
            const { currentUserId } = userInformation();
            return currentUserId ? `${API}users/${currentUserId}` : undefined;
          },
        },
      },
      {
        kind: 'override',
        key: 'category',
        patch: {
          order: 1,
          type: FieldType.ENTITY,
          url: API + 'expense-categories',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Categoría de gasto',
        },
      },
      {
        kind: 'override',
        key: 'branch',
        patch: {
          order: 2,
          type: FieldType.ENTITY,
          url: API + 'branches',
          valueField: '_iri',
          textField: 'name',
          layoutHint: 'lookup',
          label: 'Sucursal',
          defaultValue: () => {
            const { currentBranchId } = userInformation();
            return currentBranchId ? `${API}branches/${currentBranchId}` : undefined;
          },
        },
      },
      { kind: 'override', key: 'description', patch: { order: 3, layoutHint: 'fullWidth', label: 'Descripción' } },
      { kind: 'override', key: 'amount', patch: { order: 4, layoutHint: 'short', label: 'Monto', formatter: moneyCell, align: 'right' } },
      { kind: 'override', key: 'currency', patch: { order: 5, layoutHint: 'short', label: 'Moneda' } },
      { kind: 'override', key: 'expenseDate', patch: { order: 6, layoutHint: 'short', label: 'Fecha del gasto', formatter: dateCell } },
      {
        kind: 'override',
        key: 'receiptMedia',
        patch: toPatch(
          imageField(API)
            .name('receiptMedia')
            .label('Comprobante')
            .layoutHint('upload')
            .build(),
        ),
      },
      { kind: 'override', key: 'status', patch: { ...statusPatch, order: 8, layoutHint: 'short', disabled: true } },
      { kind: 'override', key: 'note', patch: { order: 9, layoutHint: 'fullWidth', label: 'Notas' } },
      { kind: 'override', key: 'reviewedBy', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'reviewedAt', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'reviewNote', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'reimbursedAt', patch: { visibleOnForm: false } },
      { kind: 'override', key: 'createdAt', patch: { visibleOnForm: false, visible: false } },
    ],
  }),
});

export const ExpenseReportPage = () => {
  const [confirmAction, setConfirmAction] = useState<{
    id: string;
    kind: 'approve' | 'reject' | 'reimburse';
  } | null>(null);

  const runAction = () => {
    if (!confirmAction) return;
    const { id, kind } = confirmAction;

    const promise =
      kind === 'approve'
        ? expenseReportService().approve(id)
        : kind === 'reject'
          ? expenseReportService().reject(id)
          : expenseReportService().reimburse(id);

    promise
      .then(() => {
        toast(
          kind === 'approve' ? 'Gasto aprobado' : kind === 'reject' ? 'Gasto rechazado' : 'Gasto marcado como reembolsado',
          'success',
        );
        setConfirmAction(null);
        dispatch(expenseReportEvents.SUCCESS);
      })
      .catch(() => {
        toast('No se pudo completar la acción', 'error');
        setConfirmAction(null);
      });
  };

  const resource = {
    ...expenseReportResource,
    rowActions: (row: ExpenseReportRecord) => {
      const actions = [];

      if (row.status === 'PENDING' && hasPermission('expense_reports.approve')) {
        actions.push({
          key: 'approve',
          text: 'Aprobar',
          icon: 'ph-check-circle',
          type: 'success' as const,
          onClick: () => setConfirmAction({ id: row.id, kind: 'approve' }),
        });
        actions.push({
          key: 'reject',
          text: 'Rechazar',
          icon: 'ph-x-circle',
          type: 'danger' as const,
          onClick: () => setConfirmAction({ id: row.id, kind: 'reject' }),
        });
      }

      if (row.status === 'APPROVED' && hasPermission('expense_reports.reimburse')) {
        actions.push({
          key: 'reimburse',
          text: 'Marcar reembolsado',
          icon: 'ph-hand-coins',
          type: 'success' as const,
          onClick: () => setConfirmAction({ id: row.id, kind: 'reimburse' }),
        });
      }

      return actions;
    },
  };

  return (
    <>
      <SchemaCrudPage resource={resource} />
      <ConfirmDialog
        open={confirmAction !== null}
        title={
          confirmAction?.kind === 'approve'
            ? 'Aprobar gasto'
            : confirmAction?.kind === 'reject'
              ? 'Rechazar gasto'
              : 'Marcar como reembolsado'
        }
        message={
          confirmAction?.kind === 'approve'
            ? '¿Confirmas que este gasto es válido y puede pasar a reembolso?'
            : confirmAction?.kind === 'reject'
              ? '¿Confirmas que este gasto debe rechazarse?'
              : '¿Confirmas que este gasto ya fue pagado al empleado?'
        }
        variant={confirmAction?.kind === 'reject' ? 'danger' : 'warning'}
        onConfirm={runAction}
        onCancel={() => setConfirmAction(null)}
      />
    </>
  );
};

ExpenseReportPage.EVENTS = expenseReportEvents;
ExpenseReportPage.API_URL = expenseReportApiUrl;
