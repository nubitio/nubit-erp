import {
  SmartCrudPage,
  defineFields,
  defineResource,
  enumField,
  imageField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubit/core';
import { Constants } from '@core/common/Constants';
import React, { useCallback, useRef, useState } from 'react';
import { ProductImportDialog } from './components/ProductImportDialog';

interface ProductRecord extends DataRecord {
  id: string;
  code: string | null;
  type: number;
  productType: string;
  productKind: 'GOOD' | 'SERVICE' | 'DIGITAL';
  inventoryBehavior: 'TRACKED' | 'NOT_TRACKED' | 'COMPOSITE';
  trackInventory: boolean;
  sku: string | null;
  barcode: string | null;
  lotTrackingStrategy: 'NONE' | 'FIFO' | 'FEFO';
  name: string;
  category: unknown;
  baseUnit: unknown;
  unitOfMeasure: unknown;
  purchasePrice: string | null;
  salePrice: string;
  stock: string | null;
  minStock: string | null;
  stocks: unknown[];
  photo: unknown;
  detractionCatalog: unknown;
}

function toPatch(field: Field): SmartCrudFieldPatch<ProductRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<ProductRecord>;
}

function renderProductPhoto(photo: { path?: string } | null | undefined, name: string) {
  const size = 36;

  if (photo?.path) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', width: '100%' }}>
        <a href={photo.path} target="_blank" rel="noreferrer">
          <img
            src={photo.path}
            alt={name}
            style={{
              width: size,
              height: size,
              borderRadius: '50%',
              objectFit: 'cover',
              display: 'block',
              boxShadow: '0 1px 3px rgba(15, 23, 42, 0.18)',
            }}
          />
        </a>
      </div>
    );
  }

  const initials =
    name
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('') || 'PR';

  return (
    <div style={{ display: 'flex', justifyContent: 'center', width: '100%' }}>
      <div
        title={name}
        aria-label={name}
        style={{
          width: size,
          height: size,
          borderRadius: '50%',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: 'linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%)',
          color: '#1e3a8a',
          fontSize: 12,
          fontWeight: 700,
          letterSpacing: '-0.02em',
        }}
      >
        {initials}
      </div>
    </div>
  );
}

function renderDetractionText(
  detraction: { code?: string; description?: string } | null | undefined,
) {
  if (!detraction) {
    return '';
  }

  const code = detraction.code?.trim();
  const description = detraction.description?.trim();

  if (code && description) {
    return `${code} - ${description}`;
  }

  return code ?? description ?? '';
}

function renderDetractionOption(item: unknown) {
  const detraction = item as
    | { code?: string; description?: string; percentage?: number | null }
    | null
    | undefined;
  const code = detraction?.code?.trim();
  const description = detraction?.description?.trim();
  const percentage = detraction?.percentage;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 2, lineHeight: 1.2 }}>
      {code ? <strong style={{ color: '#1e3a8a' }}>{code}</strong> : null}
      <span>{description ?? code ?? ''}</span>
      {typeof percentage === 'number' ? (
        <span style={{ color: '#475569', fontSize: 12 }}>{percentage}%</span>
      ) : null}
    </div>
  );
}

const productKindPatch = toPatch(
  enumField([
    { value: 'GOOD', text: 'Bien' },
    { value: 'SERVICE', text: 'Servicio' },
    { value: 'DIGITAL', text: 'Digital' },
  ])
    .name('productKind')
    .label('Clase de producto')
    .required(true)
    .build(),
);

const inventoryBehaviorPatch = toPatch(
  enumField([
    { value: 'TRACKED', text: 'Inventario controlado' },
    { value: 'NOT_TRACKED', text: 'Sin control de inventario' },
    { value: 'COMPOSITE', text: 'Compuesto' },
  ])
    .name('inventoryBehavior')
    .label('Comportamiento de inventario')
    .required(true)
    .build(),
);

const lotTrackingStrategyPatch = toPatch(
  enumField([
    { value: 'NONE', text: 'Sin lotes' },
    { value: 'FIFO', text: 'FIFO' },
    { value: 'FEFO', text: 'FEFO' },
  ])
    .name('lotTrackingStrategy')
    .label('Estrategia de lotes')
    .required(true)
    .build(),
);

const photoPatch = toPatch(
  imageField(Constants.API_BASE_URL)
    .name('photo')
    .label('Imagen')
    .width(120)
    .formatter((e) =>
      renderProductPhoto(
        (e.value as { path?: string } | null | undefined) ?? undefined,
        String(e.data?.name ?? 'Producto'),
      ),
    )
    .visibleOnForm(true)
    .build(),
);

const productResource = defineResource<ProductRecord>({
  id: 'product',
  title: 'Productos',
  apiUrl: Constants.API_BASE_URL + 'products',
  fields: [],
  fieldContract: defineFields<ProductRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'code', patch: { order: 0, width: 140 } },
      { kind: 'override', key: 'name', patch: { order: 1, width: 260 } },
      { kind: 'override', key: 'category', patch: { order: 2, width: 180 } },
      {
        kind: 'override',
        key: 'type',
        patch: {
          order: 3,
          visible: false,
          visibleOnForm: false,
          required: true,
          computed: (formData) => (formData.productKind === 'GOOD' ? 1 : 2),
        },
      },
      {
        kind: 'override',
        key: 'productKind',
        patch: { ...productKindPatch, order: 3, width: 170, defaultValue: 'GOOD' },
      },
      {
        kind: 'override',
        key: 'inventoryBehavior',
        patch: {
          ...inventoryBehaviorPatch,
          order: 4,
          width: 220,
          defaultValue: 'TRACKED',
          visibleWhen: (formData) => formData.productKind === 'GOOD',
        },
      },
      { kind: 'override', key: 'sku', patch: { order: 5, width: 140 } },
      { kind: 'override', key: 'barcode', patch: { order: 6, width: 160 } },
      {
        kind: 'override',
        key: 'lotTrackingStrategy',
        patch: {
          ...lotTrackingStrategyPatch,
          order: 7,
          width: 150,
          defaultValue: 'NONE',
          visibleWhen: (formData) => formData.inventoryBehavior === 'TRACKED',
        },
      },
      {
        kind: 'override',
        key: 'unitOfMeasure',
        patch: { order: 8, width: 160, defaultValue: '/api/unit-of-measures/1' },
      },
      {
        kind: 'override',
        key: 'purchasePrice',
        patch: { order: 9, width: 130, visibleOnForm: false },
      },
      { kind: 'override', key: 'salePrice', patch: { order: 10, width: 130 } },
      {
        kind: 'override',
        key: 'minStock',
        patch: {
          order: 12,
          width: 130,
          label: 'Stock mínimo (alerta reposición)',
          visibleWhen: (formData) => formData.inventoryBehavior === 'TRACKED',
        },
      },
      { kind: 'override', key: 'photo', patch: { ...photoPatch, order: 13 } },
      {
        kind: 'override',
        key: 'detractionCatalog',
        patch: {
          order: 14,
          width: 280,
          visible: true,
          visibleOnForm: true,
          visibleWhen: (formData) => formData.productKind === 'GOOD',
          textField: 'description',
          filters: [{ field: 'catalog', operator: '=', value: 54 }],
          itemFormatter: renderDetractionOption,
          formatter: (e) =>
            renderDetractionText(
              (e.value as { code?: string; description?: string } | null) ?? null,
            ),
        },
      },
      { kind: 'override', key: 'productType', patch: { visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'trackInventory', patch: { visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'baseUnit', patch: { visible: false, visibleOnForm: false } },
      { kind: 'override', key: 'stocks', patch: { visible: false, visibleOnForm: false } },
    ],
  }),
  sort: [{ selector: 'name', desc: false }],
});

export function ProductPage() {
  const [importOpen, setImportOpen] = useState(false);
  const gridRefreshRef = useRef<(() => void) | null>(null);

  const handleImportDone = useCallback(() => {
    // Refresh the grid after a successful import so new products are visible.
    gridRefreshRef.current?.();
  }, []);

  const productResourceWithImport = React.useMemo(
    () => ({
      ...productResource,
      toolbar: [
        ...(Array.isArray(productResource.toolbar) ? (productResource.toolbar as unknown[]) : []),
        {
          text: '',
          icon: 'ph ph-download-simple',
          hint: 'Exportar productos',
          onClick: () => window.open('/api/v1/products/export', '_blank'),
        },
        {
          text: '',
          icon: 'ph ph-upload-simple',
          hint: 'Importar productos',
          onClick: () => setImportOpen(true),
        },
      ] as typeof productResource.toolbar,
    }),
    [],
  );

  return (
    <>
      <SmartCrudPage
        resource={productResourceWithImport}
      />
      <ProductImportDialog
        isOpen={importOpen}
        onClose={() => setImportOpen(false)}
        onImportDone={handleImportDone}
      />
    </>
  );
}
