import { useNavigate } from 'react-router-dom';
import { Tabs } from '@nubitio/ui';
import './CreditNoteTabs.scss';

export type CreditNoteTab = 'issue' | 'history';

const PATHS: Record<CreditNoteTab, string> = {
  issue: '/billing/credit-notes/new',
  history: '/billing/credit-notes',
};

/**
 * Emitir nota / Historial: the two sides of the credit-note area, one route each.
 * `inset` lines the tabs up with the screen below: a Page has its own padding,
 * a CRUD grid starts nearer the edge.
 */
export function CreditNoteTabs({
  active,
  inset = 'page',
}: {
  active: CreditNoteTab;
  inset?: 'page' | 'grid';
}) {
  const navigate = useNavigate();
  return (
    <div className={`credit-note-tabs credit-note-tabs--${inset}`}>
      <Tabs
        aria-label="Notas de crédito"
        value={active}
        onChange={(tab) => navigate(PATHS[tab])}
        items={[
          { value: 'issue', label: 'Emitir nota', content: null },
          { value: 'history', label: 'Historial', content: null },
        ]}
      />
    </div>
  );
}
