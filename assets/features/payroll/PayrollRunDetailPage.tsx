/* eslint-disable react-hooks/set-state-in-effect -- payroll detail is hydrated from the selected run */
import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Badge, Button, ConfirmDialog } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';
import { formatMoney, formatMonthPeriod } from '@shared/lib/displayFormat';
import { PayrollPayDialog } from './PayrollPayDialog';
import payrollRunService, {
  type PayrollRunRecord,
  type PayslipEntryRecord,
} from './services/payrollRunService';

const STATUS_BADGE: Record<
  PayrollRunRecord['status'],
  { variant: 'success' | 'warning' | 'secondary' | 'danger'; text: string }
> = {
  DRAFT: { variant: 'warning', text: 'Borrador' },
  CALCULATED: { variant: 'secondary', text: 'Calculada' },
  APPROVED: { variant: 'secondary', text: 'Aprobada' },
  PAID: { variant: 'success', text: 'Pagada' },
};

function employeeIri(employee: PayslipEntryRecord['employee']): string | null {
  if (typeof employee === 'string') return employee;
  return employee['@id'] ?? null;
}

export const PayrollRunDetailPage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [run, setRun] = useState<PayrollRunRecord | null>(null);
  const [entries, setEntries] = useState<PayslipEntryRecord[]>([]);
  const [employeeNames, setEmployeeNames] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [confirmAction, setConfirmAction] = useState<'calculate' | 'approve' | 'pay' | null>(null);

  const reload = useCallback(() => {
    if (!id) return;
    setLoading(true);
    Promise.all([
      payrollRunService().get(id),
      payrollRunService().listEntries(id),
      payrollRunService().listEmployees(),
    ])
      .then(([runData, entriesData, employeesData]) => {
        setRun(runData);
        setEntries(entriesData);
        setEmployeeNames(
          Object.fromEntries(
            employeesData.map((employee) => [
              employee['@id'],
              `${employee.firstName} ${employee.lastName}`,
            ]),
          ),
        );
      })
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => {
    reload();
  }, [reload]);

  const runAction = () => {
    if (!id || !confirmAction) return;
    const service = payrollRunService();
    const promise =
      confirmAction === 'calculate'
        ? service.calculate(id)
        : confirmAction === 'approve'
          ? service.approve(id)
          : service.pay(id);

    promise
      .then(() => {
        toast(
          confirmAction === 'calculate'
            ? 'Planilla calculada'
            : confirmAction === 'approve'
              ? 'Corrida aprobada'
              : 'Corrida marcada como pagada',
          'success',
        );
        setConfirmAction(null);
        reload();
      })
      .catch(() => {
        toast('No se pudo completar la acción', 'error');
        setConfirmAction(null);
      });
  };

  if (loading || !run) {
    return <div className="view-wrapper-scroll">Cargando…</div>;
  }

  const badge = STATUS_BADGE[run.status];
  const netTotal = entries.reduce((sum, entry) => sum + Number(entry.netPay ?? 0), 0);

  return (
    <div className="view-wrapper-scroll">
      <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            flexWrap: 'wrap',
            gap: '1rem',
          }}
        >
          <div>
            <Button variant="secondary" size="sm" onClick={() => navigate('/payroll-runs')}>
              ← Volver a corridas
            </Button>
            <h1>Corrida de planilla — {formatMonthPeriod(run.period)}</h1>
            <p style={{ margin: '0.35rem 0 0', color: 'var(--text-secondary)' }}>
              {entries.length} {entries.length === 1 ? 'empleado' : 'empleados'} · Neto a pagar{' '}
              {formatMoney(netTotal)}
            </p>
            <Badge variant={badge.variant} outlined>
              {badge.text}
            </Badge>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
            {run.status === 'DRAFT' && (
              <Button onClick={() => setConfirmAction('calculate')}>Calcular</Button>
            )}
            {run.status === 'CALCULATED' && (
              <>
                <Button variant="secondary" onClick={() => setConfirmAction('calculate')}>
                  Recalcular
                </Button>
                <Button onClick={() => setConfirmAction('approve')}>Aprobar</Button>
              </>
            )}
            {run.status === 'APPROVED' && (
              <Button onClick={() => setConfirmAction('pay')}>Registrar pago</Button>
            )}
            {(run.status === 'APPROVED' || run.status === 'PAID') && (
              <a
                href={payrollRunService().plameExportUrl(run.id)}
                target="_blank"
                rel="noopener noreferrer"
              >
                <Button variant="secondary">Exportar PLAME</Button>
              </a>
            )}
          </div>
        </div>

        <table>
          <thead>
            {run.type === 'LIQUIDACION' ? (
              <tr>
                <th>Empleado</th>
                <th>Vacaciones truncas</th>
                <th>Gratificación trunca</th>
                <th>CTS trunca</th>
                <th>Total</th>
              </tr>
            ) : (
              <tr>
                <th>Empleado</th>
                <th>Bruto</th>
                <th>AFP/ONP</th>
                <th>EsSalud (empleador)</th>
                <th>Bonif. extraordinaria</th>
                <th>Retención 5ta</th>
                <th>Neto</th>
              </tr>
            )}
          </thead>
          <tbody>
            {entries.length === 0 ? (
              <tr>
                <td colSpan={run.type === 'LIQUIDACION' ? 5 : 7}>
                  Sin boletas — usa &quot;Calcular&quot; para generarlas.
                </td>
              </tr>
            ) : (
              entries.map((entry) => {
                const iri = employeeIri(entry.employee);
                const employeeName = (iri && employeeNames[iri]) || '—';

                if (run.type === 'LIQUIDACION') {
                  return (
                    <tr key={entry.id}>
                      <td>{employeeName}</td>
                      <td>{formatMoney(entry.vacationTruncaAmount)}</td>
                      <td>{formatMoney(entry.gratificacionTruncaAmount)}</td>
                      <td>{formatMoney(entry.ctsTruncaAmount)}</td>
                      <td>{formatMoney(entry.netPay)}</td>
                    </tr>
                  );
                }

                const pension = (
                  Number(entry.afpMandatoryContribution) +
                  Number(entry.afpInsurancePremium) +
                  Number(entry.afpCommission) +
                  Number(entry.onpAmount)
                ).toFixed(2);

                return (
                  <tr key={entry.id}>
                    <td>{employeeName}</td>
                    <td>{formatMoney(entry.grossSalary)}</td>
                    <td>{formatMoney(pension)}</td>
                    <td>{formatMoney(entry.essaludEmployerContribution)}</td>
                    <td>{formatMoney(entry.bonificacionExtraordinaria)}</td>
                    <td>{formatMoney(entry.quintaCategoriaRetention)}</td>
                    <td>{formatMoney(entry.netPay)}</td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {confirmAction === 'pay' && id && (
        <PayrollPayDialog
          runId={id}
          netLabel={formatMoney(netTotal, 'PEN')}
          onClose={() => setConfirmAction(null)}
          onDone={() => {
            setConfirmAction(null);
            reload();
          }}
        />
      )}

      <ConfirmDialog
        open={confirmAction !== null && confirmAction !== 'pay'}
        title={
          confirmAction === 'calculate'
            ? 'Calcular planilla'
            : confirmAction === 'approve'
              ? 'Aprobar corrida'
              : 'Marcar como pagada'
        }
        message={
          confirmAction === 'calculate'
            ? '¿Confirmas que quieres (re)calcular las boletas de esta corrida?'
            : confirmAction === 'approve'
              ? '¿Confirmas que esta corrida está lista para aprobarse?'
              : '¿Confirmas que esta corrida ya fue pagada a los empleados?'
        }
        variant="warning"
        onConfirm={runAction}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
};
