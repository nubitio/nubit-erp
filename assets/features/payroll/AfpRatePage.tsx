import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import {
  SchemaCrudPage,
  defineFields,
  defineResource,
  enumField,
  type DataRecord,
  type Field,
  type SmartCrudFieldPatch,
} from '@nubitio/crud';

interface AfpRateRecord extends DataRecord {
  id: number;
  afpProvider: string;
  mandatoryContributionRate: string;
  insurancePremiumRate: string;
  commissionRate: string;
  effectiveFrom: string;
  note: string | null;
}

function toPatch(field: Field): SmartCrudFieldPatch<AfpRateRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<AfpRateRecord>;
}

const afpProviderPatch = toPatch(
  enumField([
    { value: 'HABITAT', text: 'Habitat' },
    { value: 'INTEGRA', text: 'Integra' },
    { value: 'PRIMA', text: 'Prima' },
    { value: 'PROFUTURO', text: 'Profuturo' },
  ])
    .name('afpProvider')
    .label('AFP')
    .build(),
);

const AFP_RATE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: [
      'afpProvider',
      'mandatoryContributionRate',
      'insurancePremiumRate',
      'commissionRate',
      'effectiveFrom',
    ],
  },
];

const AFP_RATE_FORM_LAYOUT = {
  type: 'sections' as const,
  sections: [
    {
      label: 'AFP',
      fields: ['afpProvider', 'effectiveFrom'],
    },
    {
      label: 'Tasas (%)',
      fields: ['mandatoryContributionRate', 'insurancePremiumRate', 'commissionRate'],
    },
    {
      label: 'Notas',
      fields: ['note'],
    },
  ],
};

const resource = defineResource<AfpRateRecord>(API + 'afp-rates', {
  id: 'afp-rates',
  title: 'Tasas de AFP',
  viewMode: { mode: 'drawer', drawerSize: 'md' },
  defaultPreset: 'basic',
  columnPresets: AFP_RATE_COLUMN_PRESETS,
  formLayout: AFP_RATE_FORM_LAYOUT,
  sort: [{ selector: 'effectiveFrom', desc: true }],
  emptyState: {
    title: 'Sin tasas de AFP configuradas',
    description:
      'Registra las tasas vigentes de cada AFP (aporte obligatorio, prima de seguro, comisión). Verifica los valores con un contador antes de usarlos para calcular planillas reales.',
    icon: 'percent',
  },
  fieldContract: defineFields<AfpRateRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'afpProvider',
        patch: { ...afpProviderPatch, order: 0, layoutHint: 'short' },
      },
      {
        kind: 'override',
        key: 'effectiveFrom',
        patch: { order: 1, layoutHint: 'short', label: 'Vigente desde' },
      },
      {
        kind: 'override',
        key: 'mandatoryContributionRate',
        patch: { order: 2, layoutHint: 'short', label: 'Aporte obligatorio' },
      },
      {
        kind: 'override',
        key: 'insurancePremiumRate',
        patch: { order: 3, layoutHint: 'short', label: 'Prima de seguro' },
      },
      {
        kind: 'override',
        key: 'commissionRate',
        patch: { order: 4, layoutHint: 'short', label: 'Comisión' },
      },
      {
        kind: 'override',
        key: 'note',
        patch: { order: 5, layoutHint: 'fullWidth', label: 'Notas' },
      },
    ],
  }),
});

export const AfpRatePage = () => <SchemaCrudPage resource={resource} />;
