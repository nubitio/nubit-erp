import {Field} from "../../core/field/Field";
import {FieldBuilder} from "../../core/field/FieldBuilder";
import {FieldType} from "../../core/field/FieldType";
import {Constants} from "../../core/common/Constants";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ColumnCellTemplateData} from "devextreme/ui/data_grid";
import {DxFormEvents} from "../../core/form/DxForm";
import {consultService} from "../../services/consult.service";
import notify from "devextreme/ui/notify";

export const CustomerFields = (formRef: DxFormEvents | null) => {
    const fields: Field[] = [];

    fields.push(new FieldBuilder()
        .isIdentity(true)
        .name('ruc')
        .label('RUC/DNI')
        .validators(['validCustomerId'])
        .required(true)
        .sortable(true)
        .buttons([{
            name: 'search',
            location: 'after',
            options: {
                icon: 'search',
                onClick: (e: any) => {
                    const doc = formRef?.getFieldValue('ruc');
                    if (doc.length === 8) {
                        consultService.consultDni(doc).subscribe((response) => {
                            formRef?.setFieldValue('razon_social', response.data.name);
                        });
                    } else if (doc.length === 11) {
                        consultService.consultRuc(doc).subscribe((response) => {
                            if (!response.data.active) {
                                notify({
                                    message: 'El RUC ingresado no se encuentra activo',
                                    type: 'error',
                                    displayTime: 3000,
                                }, {position: 'top right'})
                                return;
                            }
                            formRef?.setFieldValue('razon_social', response.data.name);
                            formRef?.setFieldValue('domicilio_fiscal', response.data.address);
                        });
                    }
                }
            }
        }])
        .valueType('string')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('razon_social')
        .label('Cliente')
        .width('100%')
        .required(true)
        .sortable(true)
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.TEXTAREA)
        .name('domicilio_fiscal')
        .label('Domicilio')
        .width('100%')
        .build());
    fields.push(new FieldBuilder()
        .name('telefono')
        .label('Teléfono')
        .build());
    fields.push(new FieldBuilder()
        .name('email')
        .label('Email')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('price_list')
        .label('Tarifa')
        .url(Constants.API_BASE_URL + 'price-lists')
        .valueField('id')
        .textField('name')
        .formatter((cell: ColumnCellTemplateData) => cell.data.price_list?.name)
        .valueType('number')
        .build());
    fields.push(new FieldBuilder()
        .type(FieldType.SWITCH)
        .name('estado')
        .label('Activo')
        .align('center')
        .formatter(SharedUtils.dxBooleanFormatter)
        .defaultValue(true)
        .build());

    return fields;
}
