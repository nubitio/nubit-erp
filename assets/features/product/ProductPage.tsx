import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  imageField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import React, { useCallback, useRef, useState } from 'react';
import { ProductImportDialog } from './components/ProductImportDialog';
import './ProductPage.scss';
import { moneyCell } from '@shared/ui/crudFormatters';

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
  if (photo?.path) {
    return (
      <div className="product-photo-cell">
        <a href={photo.path} target="_blank" rel="noreferrer">
          <img
            src={photo.path}
            alt={name}
            className="product-photo-cell__image"
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
    <div className="product-photo-cell">
      <div
        title={name}
        aria-label={name}
        className="product-photo-cell__fallback"
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
    <div className="product-detraction-option">
      {code ? <strong className="product-detraction-option__code">{code}</strong> : null}
      <span>{description ?? code ?? ''}</span>
      {typeof percentage === 'number' ? (
        <span className="product-detraction-option__percentage">{percentage}%</span>
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
  imageField(API)
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
    .layoutHint('upload')
    .build(),
);

const PRODUCT_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['code', 'name', 'category', 'salePrice'],
  },
  {
    key: 'inventory',
    label: 'Inventario',
    columns: ['code', 'name', 'category', 'sku', 'barcode', 'salePrice', 'minStock'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'code',
      'name',
      'category',
      'productKind',
      'sku',
      'barcode',
      'unitOfMeasure',
      'salePrice',
      'minStock',
    ],
  },
] as const;

const productResource = defineResource<ProductRecord>(API + 'products', {
  id: 'product',
  title: 'Productos',
  defaultPreset: 'basic',
  columnPresets: PRODUCT_COLUMN_PRESETS.map((preset) => ({
    key: preset.key,
    label: preset.label,
    columns: [...preset.columns],
  })),
  viewMode: { mode: 'drawer', drawerSize: 'lg' },
  formLayout: {
    type: 'tabs',
    tabs: [
      {
        label: 'General',
        icon: 'ph-package',
        fields: ['name', 'code', 'category', 'productKind', 'salePrice', 'photo'],
      },
      {
        label: 'Inventario',
        icon: 'ph-warehouse',
        fields: [
          'inventoryBehavior',
          'sku',
          'barcode',
          'unitOfMeasure',
          'lotTrackingStrategy',
          'minStock',
        ],
      },
      {
        label: 'SUNAT',
        icon: 'ph-scales',
        fields: ['detractionCatalog'],
      },
    ],
  },
  fieldContract: defineFields<ProductRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { order: 0, layoutHint: 'fullWidth', label: 'Nombre' } },
      { kind: 'override', key: 'code', patch: { order: 1, layoutHint: 'short', label: 'Código' } },
      { kind: 'override', key: 'category', patch: { order: 2, layoutHint: 'lookup', label: 'Categoría' } },
      {
        kind: 'override',
        key: 'salePrice',
        patch: { order: 3, layoutHint: 'money', align: 'right', label: 'Precio de venta', formatter: moneyCell },
      },
      {
        kind: 'override',
        key: 'type',
        patch: {
          order: 4,
          visible: false,
          visibleOnForm: false,
          required: true,
          computed: (formData) => (formData.productKind === 'GOOD' ? 1 : 2),
        },
      },
      {
        kind: 'override',
        key: 'productKind',
        patch: {
          ...productKindPatch,
          order: 4,
          layoutHint: 'short',
          defaultValue: 'GOOD',
          filterable: false,
        },
      },
      {
        kind: 'override',
        key: 'inventoryBehavior',
        patch: {
          ...inventoryBehaviorPatch,
          order: 10,
          layoutHint: 'fullWidth',
          visible: false,
          defaultValue: 'TRACKED',
          filterable: false,
          visibleWhen: (formData) => formData.productKind === 'GOOD',
        },
      },
      { kind: 'override', key: 'sku', patch: { order: 11, layoutHint: 'short', label: 'SKU' } },
      { kind: 'override', key: 'barcode', patch: { order: 12, layoutHint: 'short', label: 'Código de barras' } },
      {
        kind: 'override',
        key: 'unitOfMeasure',
        patch: {
          order: 13,
          layoutHint: 'lookup',
          label: 'Unidad de medida',
          defaultValue: '/api/unit-of-measures/1',
          filterable: false,
        },
      },
      {
        kind: 'override',
        key: 'lotTrackingStrategy',
        patch: {
          ...lotTrackingStrategyPatch,
          order: 14,
          layoutHint: 'short',
          visible: false,
          defaultValue: 'NONE',
          filterable: false,
          visibleWhen: (formData) => formData.inventoryBehavior === 'TRACKED',
        },
      },
      {
        kind: 'override',
        key: 'minStock',
        patch: {
          order: 15,
          layoutHint: 'short',
          align: 'right',
          label: 'Stock mínimo',
          filterable: false,
          visibleWhen: (formData) => formData.inventoryBehavior === 'TRACKED',
        },
      },
      {
        kind: 'override',
        key: 'purchasePrice',
        patch: { order: 16, visible: false, visibleOnForm: false, filterable: false },
      },
      {
        kind: 'override',
        key: 'photo',
        patch: { ...photoPatch, order: 5, visible: false, filterable: false },
      },
      {
        kind: 'override',
        key: 'detractionCatalog',
        patch: {
          order: 20,
          layoutHint: 'fullWidth',
          visible: false,
          visibleOnForm: true,
          filterable: false,
          visibleWhen: (formData) =>
            formData.productKind === 'GOOD' ||
            formData.productKind === 'SERVICE' ||
            formData.productKind === 'DIGITAL',
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
      auditTrail: {
        enabled: true,
        apiUrl: (id: string | number) => `/api/audit-trail/product/${id}`,
        recordSubtitle: (row: DataRecord) => {
          const name = typeof row.name === 'string' ? row.name : null;
          const code = typeof row.code === 'string' ? row.code : null;
          const parts = [name, code ? `Cód. ${code}` : null].filter(Boolean);
          return parts.length > 0 ? parts.join(' · ') : undefined;
        },
      },
      toolbar: {
        utility: [
          {
            text: 'Exportar productos',
            icon: 'ph-download-simple',
            hint: 'Exportar productos',
            onClick: () => window.open('/api/v1/products/export', '_blank'),
          },
          {
            text: 'Importar productos',
            icon: 'ph-upload-simple',
            hint: 'Importar productos',
            onClick: () => setImportOpen(true),
          },
        ],
      },
    }),
    [],
  );

  return (
    <>
      <SchemaCrudPage
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
