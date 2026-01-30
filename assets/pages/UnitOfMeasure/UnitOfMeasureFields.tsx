import { Field } from '../../core/field/Field';
import { FieldBuilder } from '../../core/field/FieldBuilder';
import { FieldType } from '../../core/field/FieldType';
import { SharedUtils } from '../../core/utils/SharedUtils';
import { ColumnCellTemplateData } from 'devextreme/ui/data_grid';

export const UnitOfMeasureFields = () => {
  const fields: Field[] = [];

  fields.push(
    new FieldBuilder()
      .isIdentity(true)
      .visible(false)
      .type(FieldType.NONE)
      .name('id')
      .label('Id')
      .required(true)
      .valueType('number')
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.TEXT)
      .name('name')
      .label('Nombre')
      .width('100%')
      .required(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.SELECT)
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
  fields.push(
    new FieldBuilder()
      .type(FieldType.ENUM)
      .name('type')
      .label('Tipo')
      .data([
        { value: 1, text: 'Unidad' },
        { value: 2, text: 'Peso' },
        { value: 3, text: 'Volumen' },
        { value: 4, text: 'Longitud' },
        { value: 5, text: 'Tiempo' },
      ])
      .formatter((cell: ColumnCellTemplateData) => {
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
  fields.push(
    new FieldBuilder()
      .type(FieldType.NUMBER)
      .name('ratio')
      .label('Ratio')
      .helpText('Relación con la unidad base')
      .align('center')
      .precision(5)
      .defaultValue(1)
      .required(true)
      .sendAsString(true)
      .build(),
  );
  fields.push(
    new FieldBuilder()
      .type(FieldType.SWITCH)
      .name('reference')
      .label('Es unidad base')
      .helpText('Indica si es la unidad base')
      .align('center')
      .defaultValue(false)
      .formatter((cell: ColumnCellTemplateData) => SharedUtils.booleanFormatter(cell.value))
      .build(),
  );

  return fields;
};
