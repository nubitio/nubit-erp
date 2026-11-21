import type { ReactNode } from 'react';
import { Badge, Button } from '@nubitio/ui';
import { SettingsRow } from './SettingsRow';

/** Whether a record is in use; inactive ones stay listed so they can be turned back on. */
export const ActiveBadge = ({ active }: { active: boolean }) => (
  <Badge variant={active ? 'success' : 'secondary'}>{active ? 'Activa' : 'Inactiva'}</Badge>
);

/** One record of a short list: what it is, a line about it, its state and "Editar". */
export const RecordRow = ({
  title,
  description,
  active,
  editLabel,
  onEdit,
}: {
  title: string;
  description?: ReactNode;
  active: boolean;
  /** Names the record for assistive technology, e.g. "Editar sucursal Principal". */
  editLabel: string;
  /** Left out when the user may not change records. */
  onEdit?: () => void;
}) => (
  <SettingsRow label={title} description={description}>
    <ActiveBadge active={active} />
    {onEdit && (
      <Button type="button" variant="secondary" size="sm" aria-label={editLabel} onClick={onEdit}>
        Editar
      </Button>
    )}
  </SettingsRow>
);
