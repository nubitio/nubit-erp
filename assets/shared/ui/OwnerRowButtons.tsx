import type { MouseEvent, ReactNode } from 'react';
import { Button } from '@nubitio/ui';

type OwnerRowButton = {
  label: string;
  onClick: () => void;
  variant?: 'primary' | 'secondary';
};

export function OwnerRowButtons({ actions }: { actions: OwnerRowButton[] }): ReactNode {
  return (
    <span className="owner-row-buttons" onClick={(event: MouseEvent) => event.stopPropagation()}>
      {actions.map((action) => (
        <Button key={action.label} size="sm" variant={action.variant ?? 'primary'} onClick={action.onClick}>
          {action.label}
        </Button>
      ))}
    </span>
  );
}
