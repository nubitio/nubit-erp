import { Drawer } from '@nubitio/ui';
import { DocumentStatusTimeline } from './DocumentStatusTimeline';
import type { DocumentLifecycleInput } from '../lib/buildSunatLifecycleSteps';
import './DocumentStatusDrawer.scss';

export interface DocumentStatusDrawerProps {
  document: DocumentLifecycleInput | null;
  subtitle?: string;
  isOpen: boolean;
  onClose: () => void;
}

export function DocumentStatusDrawer({
  document,
  subtitle,
  isOpen,
  onClose,
}: DocumentStatusDrawerProps) {
  return (
    <Drawer
      isOpen={isOpen}
      onClose={onClose}
      title={
        <div className="nb-document-status-drawer__header">
          {subtitle && <p className="nb-document-status-drawer__record">{subtitle}</p>}
          <p className="nb-document-status-drawer__label">Estado SUNAT</p>
        </div>
      }
      width={400}
      side="right"
      scrim="subtle"
      closeLabel="Cerrar estado SUNAT"
      aria-label="Estado SUNAT"
      className="nb-document-status-drawer"
    >
      {document && (
        <DocumentStatusTimeline
          document={document}
          description="Etapas del comprobante. Las fechas de envío y respuesta SUNAT se mostrarán cuando estén disponibles en el registro."
        />
      )}
    </Drawer>
  );
}