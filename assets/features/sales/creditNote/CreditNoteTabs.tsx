import { useNavigate } from 'react-router-dom';
import { Tabs } from '@nubitio/ui';

export type CreditNoteTab = 'issue' | 'history';

const PATHS: Record<CreditNoteTab, string> = {
  issue: '/billing/credit-notes/new',
  history: '/billing/credit-notes',
};

/** Emitir nota / Historial: the two sides of the credit-note area, one route each. */
export function CreditNoteTabs({ active }: { active: CreditNoteTab }) {
  const navigate = useNavigate();
  return (
    <Tabs
      aria-label="Notas de crédito"
      value={active}
      onChange={(tab) => navigate(PATHS[tab])}
      items={[
        { value: 'issue', label: 'Emitir nota', content: null },
        { value: 'history', label: 'Historial', content: null },
      ]}
    />
  );
}
