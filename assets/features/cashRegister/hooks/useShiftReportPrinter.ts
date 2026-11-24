import { useCallback, useMemo } from 'react';
import { createInvoicePrinter } from '@features/sales';
import toast from '@shared/lib/SharedUtils';
import { cashSessionService } from '../services/cashSessionService';

/**
 * Prints the X cut or the Z report of a shift on the till printer, the way the sale ticket is printed: the server
 * builds the ESC/POS bytes and the browser sends them (Web USB or the local agent, as the business configured).
 */
export function useShiftReportPrinter() {
  const printer = useMemo(() => createInvoicePrinter(), []);

  return useCallback(
    async (sessionId: number) => {
      try {
        await printer.printDocument(() => cashSessionService.printReport(sessionId));
        toast('Reporte enviado a la impresora', 'success');
      } catch (error) {
        toast(
          error instanceof Error && error.message
            ? error.message
            : 'No se pudo imprimir el reporte. Revisa la impresora.',
          'error',
        );
      }
    },
    [printer],
  );
}
