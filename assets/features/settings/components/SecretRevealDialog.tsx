import { useState } from 'react';
import { AppDialog, Button, Code } from '@nubitio/ui';
import toast from '@shared/lib/SharedUtils';

interface SecretRevealDialogProps {
  open: boolean;
  title: string;
  secret: string;
  hint?: string;
  onClose: () => void;
}

/**
 * Shows a freshly minted secret exactly once. There is no way back to it — the
 * server only kept a hash — so the copy button and the warning are the whole
 * point of this dialog.
 */
export const SecretRevealDialog = ({
  open,
  title,
  secret,
  hint,
  onClose,
}: SecretRevealDialogProps) => {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(secret);
      setCopied(true);
      toast('Copiado al portapapeles', 'success');
    } catch {
      toast('No se pudo copiar; selecciónalo manualmente', 'error');
    }
  };

  return (
    <AppDialog
      open={open}
      title={title}
      onClose={onClose}
      maxWidth="32rem"
      footer={
        <div className="integration-dialog__footer">
          <Button variant="ghost" onClick={onClose}>
            Ya lo guardé
          </Button>
          <Button variant="primary" onClick={copy}>
            {copied ? 'Copiado' : 'Copiar'}
          </Button>
        </div>
      }
    >
      <p className="integration-secret__warning">
        Cópialo ahora. No se vuelve a mostrar: sólo guardamos un hash.
      </p>
      <Code className="integration-secret__value">{secret}</Code>
      {hint ? <p className="integration-secret__hint">{hint}</p> : null}
    </AppDialog>
  );
};
