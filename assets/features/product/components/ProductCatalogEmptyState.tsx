import { Button, Card, EmptyState } from '@nubitio/ui';
import { useState } from 'react';
import { downloadProductImportTemplate } from '../model/downloadProductImportTemplate';
import './ProductCatalogEmptyState.scss';

export function ProductCatalogEmptyState({
  onCreate,
  onImport,
}: {
  onCreate?: () => void;
  onImport?: () => void;
}) {
  const canStart = Boolean(onCreate || onImport);
  const [downloading, setDownloading] = useState(false);
  const [downloadError, setDownloadError] = useState(false);
  const downloadTemplate = async () => {
    setDownloading(true);
    setDownloadError(false);
    try {
      await downloadProductImportTemplate();
    } catch {
      setDownloadError(true);
    } finally {
      setDownloading(false);
    }
  };
  return (
    <div className="product-catalog-empty">
      <EmptyState
        size="lg"
        title={canStart ? 'Agrega tus productos para empezar a vender' : 'Todavía no hay productos'}
        description={
          canStart
            ? 'Con tu catálogo listo podrás vender en el punto de venta, emitir boletas y facturas y controlar tu stock.'
            : 'Cuando se agreguen productos al catálogo, podrás consultarlos aquí.'
        }
        illustration={<img src="/assets/images/design-system/catalog-empty.svg" alt="" />}
        action={
          canStart ? (
            <div className="product-catalog-empty__content">
              <div className="product-catalog-empty__options">
                {onCreate && (
                  <Card variant="panel" className="product-catalog-empty__option">
                    <span className="product-catalog-empty__option-icon">
                      <img src="/assets/images/design-system/catalog-create.svg" alt="" />
                    </span>
                    <h2>Uno por uno</h2>
                    <p>Completa los datos de cada producto y su precio de venta.</p>
                    <Button variant="secondary" onClick={onCreate}>
                      Crear producto
                    </Button>
                  </Card>
                )}
                {onImport && (
                  <Card
                    variant="panel"
                    className="product-catalog-empty__option product-catalog-empty__option--import"
                  >
                    <span className="product-catalog-empty__option-icon">
                      <img src="/assets/images/design-system/catalog-import.svg" alt="" />
                    </span>
                    <h2>Importar desde Excel</h2>
                    <p>Usa la plantilla con las categorías y unidades de tu empresa.</p>
                    <Button variant="primary" onClick={onImport}>
                      Subir archivo
                    </Button>
                  </Card>
                )}
              </div>
              {onImport && (
                <Button
                  variant="ghost"
                  onClick={() => void downloadTemplate()}
                  disabled={downloading}
                >
                  Descargar plantilla de Excel
                </Button>
              )}
              {downloadError && (
                <p role="alert">No se pudo descargar la plantilla. Intenta de nuevo.</p>
              )}
            </div>
          ) : undefined
        }
      />
    </div>
  );
}
