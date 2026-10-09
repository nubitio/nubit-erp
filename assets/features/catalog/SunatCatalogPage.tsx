import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  switchField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { sunatCatalogEndpoints } from './api/catalogEndpoints';

interface SunatCatalogRecord extends DataRecord {
  id: number;
  catalog: number;
  code: string;
  description: string;
  percentage: number | null;
  active: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<SunatCatalogRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<SunatCatalogRecord>;
}

const activePatch = toPatch(
  switchField()
    .name('active')
    .label('Activo')
    .defaultValue(true)
    .formatter((cell) => SharedUtils.booleanFormatter(cell.value as boolean))
    .build(),
);

const SUNAT_CATALOG_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['catalog', 'code', 'description'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: ['catalog', 'code', 'description', 'percentage', 'active'],
  },
];

const SUNAT_CATALOG_FORM_LAYOUT = {
  type: 'tabs' as const,
  tabs: [
    {
      label: 'General',
      icon: 'ph-list-bullets',
      fields: ['catalog', 'code', 'description'],
    },
    {
      label: 'Detalle',
      icon: 'ph-percent',
      fields: ['percentage', 'active'],
    },
  ],
};

const resource = defineResource<SunatCatalogRecord>(sunatCatalogEndpoints.list(), {
  id: 'sunat-catalog',
  title: 'Catálogos SUNAT',
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  defaultPreset: 'basic',
  columnPresets: SUNAT_CATALOG_COLUMN_PRESETS,
  formLayout: SUNAT_CATALOG_FORM_LAYOUT,
  emptyState: {
    title: 'Sin catálogos SUNAT',
    description: 'Registra códigos oficiales para detracción y otros catálogos tributarios.',
    icon: 'list-bullets',
  },
  fieldContract: defineFields<SunatCatalogRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'catalog', patch: { order: 0, width: 100, label: 'Catálogo' } },
      { kind: 'override', key: 'code', patch: { order: 1, width: 100, label: 'Código' } },
      {
        kind: 'override',
        key: 'description',
        patch: { order: 2, width: 320, label: 'Descripción' },
      },
      {
        kind: 'override',
        key: 'percentage',
        patch: { order: 3, width: 110, label: 'Porcentaje', align: 'right' },
      },
      { kind: 'override', key: 'active', patch: activePatch },
    ],
  }),
});

export const SunatCatalogPage = () => <SchemaCrudPage resource={resource} />;
