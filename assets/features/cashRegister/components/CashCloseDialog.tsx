import { AppDialog } from '@nubitio/ui';
import { CashCloseFlow } from './CashCloseFlow';

interface Props {
  open: boolean;
  sessionId: number | string;
  onClose: () => void;
  /** The server closed the session; the caller refreshes its state. */
  onClosed: () => void | Promise<void>;
}

/** The cash count and closing, without leaving the point of sale. */
export function CashCloseDialog({ open, sessionId, onClose, onClosed }: Props) {
  return (
    <AppDialog open={open} title="Cerrar caja" onClose={onClose} width={1120} maxWidth="96vw">
      <CashCloseFlow
        key={String(sessionId)}
        embedded
        sessionId={String(sessionId)}
        onContinue={onClose}
        onClosed={() => {
          void onClosed();
        }}
      />
    </AppDialog>
  );
}
