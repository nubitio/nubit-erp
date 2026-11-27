import { useEffect, useRef, useState, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import { IconButton } from '@nubitio/ui';
import type { GridHandle } from '@nubitio/crud';

export function ReceivableGridFrame({
  gridRef,
  className,
  children,
}: {
  gridRef: RefObject<GridHandle | null>;
  className: string;
  children: ReactNode;
}) {
  const frameRef = useRef<HTMLDivElement>(null);
  const [footerStatus, setFooterStatus] = useState<HTMLElement | null>(null);

  useEffect(() => {
    setFooterStatus(
      frameRef.current?.querySelector<HTMLElement>('.nb-datagrid__pager-status') ?? null,
    );
  }, [children]);

  return (
    <div ref={frameRef} className={className}>
      {children}
      {footerStatus &&
        createPortal(
          <IconButton
            className="accounts-receivable-page__footer-refresh"
            icon="ph ph-arrow-clockwise"
            label="Actualizar comprobantes"
            onClick={() => gridRef.current?.refresh()}
          />,
          footerStatus,
        )}
    </div>
  );
}
