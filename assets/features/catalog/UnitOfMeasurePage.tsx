import { SmartCrudPage, defineResource } from '@core/crud';
import { defineFields, type SmartCrudFieldPatch } from '@core/crud/fieldContract';
import type { Field } from '@core/field/Field';
import { enumField, selectField, switchField } from '@core/field/FieldBuilders';
import { unitOfMeasureEvents } from '@core/event/domainEvents';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { unitOfMeasureEndpoints } from './api/catalogEndpoints';
import type { DataRecord } from '@core/data/DataRecord';

interface UnitOfMeasureRecord extends DataRecord {
  id: number;
  name: string;
  code: string;
  type: number;
  ratio: string;
  reference: boolean;
}

function toPatch(field: Field): SmartCrudFieldPatch<UnitOfMeasureRecord> {
  const { name: _name, ...patch } = field;
  void _name;

  return patch as SmartCrudFieldPatch<UnitOfMeasureRecord>;
}

const codePatch = toPatch(
  selectField()
    .name('code')
    .label('Código')
    .data([
      { value: '4A', text: 'BOBINAS' },
      { value: 'BJ', text: 'BALDE' },
      { value: 'BLL', text: 'BARRILES' },
      { value: 'BG', text: 'BOLSA' },
      { value: 'BO', text: 'BOTELLAS' },
      { value: 'BX', text: 'CAJA' },
      { value: 'CT', text: 'CARTONES' },
      { value: 'CMK', text: 'CENTIMETRO CUADRADO' },
      { value: 'CMQ', text: 'CENTIMETRO CUBICO' },
      { value: 'CMT', text: 'CENTIMETRO LINEAL' },
      { value: 'CEN', text: 'CIENTO DE UNIDADES' },
      { value: 'CY', text: 'CILINDRO' },
      { value: 'CJ', text: 'CONOS' },
      { value: 'DZN', text: 'DOCENA' },
      { value: 'DZP', text: 'DOCENA POR 10**6' },
      { value: 'BE', text: 'FARDO' },
      { value: 'GLI', text: 'GALON INGLES (4,545956L)' },
      { value: 'GRM', text: 'GRAMO' },
      { value: 'GRO', text: 'GRUESA' },
      { value: 'HLT', text: 'HECTOLITRO' },
      { value: 'LEF', text: 'HOJA' },
      { value: 'SET', text: 'JUEGO' },
      { value: 'KGM', text: 'KILOGRAMO' },
      { value: 'KTM', text: 'KILOMETRO' },
      { value: 'KWH', text: 'KILOVATIO HORA' },
      { value: 'KT', text: 'KIT' },
      { value: 'CA', text: 'LATAS' },
      { value: 'LBR', text: 'LIBRAS' },
      { value: 'LTR', text: 'LITRO' },
      { value: 'MWH', text: 'MEGAWATT HORA' },
      { value: 'MTR', text: 'METRO' },
      { value: 'MTK', text: 'METRO CUADRADO' },
      { value: 'MTQ', text: 'METRO CUBICO' },
      { value: 'MGM', text: 'MILIGRAMOS' },
      { value: 'MLT', text: 'MILILITRO' },
      { value: 'MMT', text: 'MILIMETRO' },
      { value: 'MMK', text: 'MILIMETRO CUADRADO' },
      { value: 'MMQ', text: 'MILIMETRO CUBICO' },
      { value: 'MIL', text: 'MILLARES' },
      { value: 'UM', text: 'MILLON DE UNIDADES' },
      { value: 'ONZ', text: 'ONZAS' },
      { value: 'PF', text: 'PALETAS' },
      { value: 'PK', text: 'PAQUETE' },
      { value: 'PR', text: 'PAR' },
      { value: 'FOT', text: 'PIES' },
      { value: 'FTK', text: 'PIES CUADRADOS' },
      { value: 'FTQ', text: 'PIES CUBICOS' },
      { value: 'C62', text: 'PIEZAS' },
      { value: 'PG', text: 'PLACAS' },
      { value: 'ST', text: 'PLIEGO' },
      { value: 'INH', text: 'PULGADAS' },
      { value: 'RM', text: 'RESMA' },
      { value: 'DR', text: 'TAMBOR' },
      { value: 'STN', text: 'TONELADA CORTA' },
      { value: 'LTN', text: 'TONELADA LARGA' },
      { value: 'TNE', text: 'TONELADAS' },
      { value: 'TU', text: 'TUBOS' },
      { value: 'NIU', text: 'UNIDAD (BIENES)' },
      { value: 'ZZ', text: 'UNIDAD (SERVICIOS)' },
      { value: 'GLL', text: 'US GALON (3,7843 L)' },
      { value: 'YRD', text: 'YARDA' },
      { value: 'YDK', text: 'YARDA CUADRADA' },
    ])
    .build(),
);

const typePatch = toPatch(
  enumField([
    { value: 1, text: 'Unidad' },
    { value: 2, text: 'Peso' },
    { value: 3, text: 'Volumen' },
    { value: 4, text: 'Longitud' },
    { value: 5, text: 'Tiempo' },
  ])
    .name('type')
    .label('Tipo')
    .formatter((cell) => {
      switch (cell.value) {
        case 1:
          return 'Unidad';
        case 2:
          return 'Peso';
        case 3:
          return 'Volumen';
        case 4:
          return 'Longitud';
        case 5:
          return 'Tiempo';
        default:
          return '';
      }
    })
    .defaultValue(1)
    .required(true)
    .build(),
);

const referencePatch = toPatch(
  switchField()
    .name('reference')
    .label('Es unidad base')
    .helpText('Indica si es la unidad base')
    .align('center')
    .defaultValue(false)
    .formatter((cell) => SharedUtils.booleanFormatter(cell.value))
    .build(),
);

const resource = defineResource<UnitOfMeasureRecord>({
  id: 'unitOfMeasure',
  title: 'Unidades de medida',
  apiUrl: unitOfMeasureEndpoints.list(),
  fields: [],
  events: unitOfMeasureEvents,
  fieldContract: defineFields<UnitOfMeasureRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      { kind: 'override', key: 'name', patch: { width: '100%' } },
      { kind: 'override', key: 'code', patch: codePatch },
      { kind: 'override', key: 'type', patch: typePatch },
      {
        kind: 'override',
        key: 'ratio',
        patch: {
          label: 'Ratio',
          helpText: 'Relación con la unidad base',
          align: 'center',
          precision: 5,
          defaultValue: 1,
          required: true,
          sendAsString: true,
        },
      },
      { kind: 'override', key: 'reference', patch: referencePatch },
    ],
  }),
});
export const UnitOfMeasurePage = () => <SmartCrudPage resource={resource} />;
