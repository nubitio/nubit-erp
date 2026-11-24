import { useQuery } from '@tanstack/react-query';
import { Alert, AppDialog, Button, Skeleton } from '@nubitio/ui';
import { cashSessionService } from '../services/cashSessionService';
import { useShiftReportPrinter } from '../hooks/useShiftReportPrinter';
import { ShiftTicket } from './ShiftTicket';
import './ShiftTicket.scss';

interface Props {
  open: boolean;
  sessionId: number;
  onClose: () => void;
}

/** The X cut (open shift) or Z report (closed one) of a shift, ready to print on the till printer. */
export function ShiftTicketDialog({ open, sessionId, onClose }: Props) {
  const report = useQuery({
    queryKey: ['cash-session-report', sessionId],
    queryFn: () => cashSessionService.getReport(sessionId),
    enabled: open,
    // A cut is only worth something if it is current.
    staleTime: 0,
    refetchOnMount: 'always',
  });
  const printOnTillPrinter = useShiftReportPrinter();
  const closed = report.data?.shift.status === 'closed';

  return (
    <AppDialog
      open={open}
      onClose={onClose}
      title={closed ? 'Reporte Z · cierre de turno' : 'Reporte X · corte parcial'}
      width={440}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cerrar
          </Button>
          <Button
            variant="secondary"
            data-testid="shift-ticket-print-browser"
            disabled={!report.data}
            onClick={() => window.print()}
          >
            Imprimir del navegador
          </Button>
          <Button
            variant="primary"
            icon="ph ph-printer"
            data-testid="shift-ticket-print"
            disabled={!report.data}
            onClick={() => void printOnTillPrinter(sessionId)}
          >
            Imprimir en ticketera
          </Button>
        </>
      }
    >
      {report.isError ? (
        <Alert tone="danger">No se pudo cargar el reporte del turno.</Alert>
      ) : report.data ? (
        <ShiftTicket report={report.data} />
      ) : (
        <Skeleton variant="text" lines={10} />
      )}
    </AppDialog>
  );
}
