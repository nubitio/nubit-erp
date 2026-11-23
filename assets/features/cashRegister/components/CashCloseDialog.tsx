import { AppDialog } from '@nubitio/ui';
import { CashCloseFlow } from './CashCloseFlow';

interface Props {
  open: boolean;
  sessionId: number | string;
  onClose: () => void;
  /** The server closed the session; the caller refreshes its state. */
  onClosed: () => void | Promise<void>;
  /** Lets the cashier look at the shift's sales while counting. */
  onViewSales?: () => void;
}

/** The cash count and closing, without leaving the point of sale. */
export function CashCloseDialog({ open, sessionId, onClose, onClosed, onViewSales }: Props) {
  return (
    <AppDialog open={open} title="Cerrar caja" onClose={onClose} width={1120} maxWidth="96vw">
      <CashCloseFlow
        key={String(sessionId)}
        embedded
        sessionId={String(sessionId)}
        onViewSales={onViewSales}
        onContinue={onClose}
        onClosed={() => {
          void onClosed();
        }}
      />
    </AppDialog>
  );
}
