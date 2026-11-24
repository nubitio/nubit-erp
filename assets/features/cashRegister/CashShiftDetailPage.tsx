import './CashShiftDetail.scss';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Alert, Badge, Button, Skeleton, Tabs } from '@nubitio/ui';
import { hasPermission } from '@shared/permissions';
import cashRegisterService from './services/cashRegisterService';
import { cashSessionService, type CashMovementKind } from './services/cashSessionService';
import { ShiftLedgerTable } from './components/ShiftLedgerTable';
import { ShiftSidePanels } from './components/ShiftSidePanels';
import { ShiftTicketDialog } from './components/ShiftTicketDialog';
import { RegisterMovementDialog } from './components/RegisterMovementDialog';
import { openClosureReport } from './model/closureReport';
import { formatDate, formatTime, shiftCode } from './model/format';
import { Link } from 'react-router-dom';

type DetailTab = 'movements' | 'sales';

/** `/cash-session/:id/movements`: one shift, everything that happened in it and what the drawer should hold. */
export const CashShiftDetailPage = () => {
  const { id = '' } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [tab, setTab] = useState<DetailTab>('movements');
  const [ticketOpen, setTicketOpen] = useState(false);
  const [dialog, setDialog] = useState<CashMovementKind | null>(null);
  const sessionId = Number(id);

  const ledger = useQuery({
    queryKey: ['cash-session-ledger', sessionId],
    queryFn: () => cashSessionService.getLedger(sessionId),
    enabled: Number.isFinite(sessionId),
    refetchInterval: 30_000,
  });
  const report = useQuery({
    queryKey: ['cash-x-report', sessionId],
    queryFn: () =>
      cashRegisterService()
        .getXReport(String(sessionId))
        .then((response) => response.data),
    enabled: Number.isFinite(sessionId),
    refetchInterval: 30_000,
  });

  const shift = ledger.data;
  const isOpen = shift?.status === 'open';
  const entries = shift?.entries ?? [];

  return (
    <div className="shift-detail" data-testid="shift-detail">
      <header className="shift-detail__header">
        <div>
          <nav className="shift-detail__crumbs" aria-label="Ruta">
            <Link to="/cashbox/dashboard">Cajas y turnos</Link> / <span>Turno {shiftCode(id)}</span>
          </nav>
          <h1 className="shift-detail__title">
            Turno {shiftCode(id)}
            {shift && (
              <Badge variant={isOpen ? 'success' : 'secondary'} pill outlined>
                {isOpen ? 'Abierto' : 'Cerrado'}
              </Badge>
            )}
          </h1>
          {shift && (
            <p className="shift-detail__subtitle">
              {shift.cashRegisterName}
              {shift.cashier ? ` · ${shift.cashier}` : ''} · Abierto el {formatDate(shift.openedAt)}{' '}
              a las {formatTime(shift.openedAt)}
            </p>
          )}
        </div>
        {shift && !isOpen && (
          <div className="shift-detail__actions">
            <Button
              variant="secondary"
              icon="ph ph-printer"
              data-testid="shift-ticket-open"
              onClick={() => setTicketOpen(true)}
            >
              Reporte Z
            </Button>
            <Button
              variant="secondary"
              icon="ph ph-file-pdf"
              data-testid="shift-pdf"
              onClick={() => openClosureReport(sessionId)}
            >
              Reporte PDF
            </Button>
          </div>
        )}
        {isOpen && (
          <div className="shift-detail__actions">
            <Button
              variant="secondary"
              icon="ph ph-printer"
              data-testid="shift-ticket-open"
              onClick={() => setTicketOpen(true)}
            >
              Reporte X
            </Button>
            {hasPermission('cash.movement') && (
              <>
                <Button
                  variant="secondary"
                  icon="ph ph-arrow-down"
                  data-testid="shift-register-income"
                  onClick={() => setDialog('income')}
                >
                  Registrar ingreso
                </Button>
                <Button
                  variant="secondary"
                  icon="ph ph-arrow-up"
                  data-testid="shift-register-expense"
                  onClick={() => setDialog('expense')}
                >
                  Registrar egreso
                </Button>
                <Button
                  variant="secondary"
                  icon="ph ph-vault"
                  data-testid="shift-register-withdrawal"
                  onClick={() => setDialog('withdrawal')}
                >
                  Retirar efectivo
                </Button>
              </>
            )}
            {hasPermission('cash.close') && (
              <Button
                variant="primary"
                icon="ph ph-lock-simple"
                data-testid="shift-close"
                onClick={() => navigate(`/cash-session/${sessionId}/close`)}
              >
                Cerrar turno
              </Button>
            )}
          </div>
        )}
      </header>

      {ledger.isError ? (
        <Alert tone="danger">No se pudo cargar el turno.</Alert>
      ) : (
        <div className="shift-detail__body">
          <section className="shift-main">
            {ledger.isLoading ? (
              <Skeleton variant="text" lines={8} />
            ) : (
              <Tabs<DetailTab>
                aria-label="Detalle del turno"
                value={tab}
                onChange={setTab}
                items={[
                  {
                    value: 'movements',
                    label: 'Movimientos',
                    content: (
                      <ShiftLedgerTable
                        key="movements"
                        entries={entries}
                        emptyMessage="Este turno todavía no tiene movimientos."
                      />
                    ),
                  },
                  {
                    value: 'sales',
                    label: 'Ventas del turno',
                    content: (
                      <ShiftLedgerTable
                        key="sales"
                        entries={entries.filter((entry) => entry.kind === 'sale')}
                        filters={['sale']}
                        emptyMessage="Este turno todavía no tiene ventas."
                      />
                    ),
                  },
                ]}
              />
            )}
          </section>
          <ShiftSidePanels report={report.data} />
        </div>
      )}

      <ShiftTicketDialog
        open={ticketOpen}
        sessionId={sessionId}
        onClose={() => setTicketOpen(false)}
      />

      {dialog && (
        <RegisterMovementDialog
          open
          kind={dialog}
          sessionId={sessionId}
          onClose={() => setDialog(null)}
        />
      )}
    </div>
  );
};
