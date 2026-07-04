import { ContextMenu, type ContextMenuItem } from '@nubitio/ui';
import { CP_STRINGS } from '../../i18n/es';

interface CpRowActionsProps {
  items: ContextMenuItem[];
  triggerLabel?: string;
}

export const CpRowActions = ({
  items,
  triggerLabel = CP_STRINGS.rowActions,
}: CpRowActionsProps) => {
  if (items.length === 0) return null;

  return (
    <ContextMenu
      items={items}
      align="right"
      triggerLabel={triggerLabel}
      visible={items.length > 0}
    />
  );
};