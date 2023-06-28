import Form, {SimpleItem} from "devextreme-react/form";
import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {bus} from "../event/EventBus";
import {useRef} from "react";

export const DxForm = (props: any) => {
    const formRef = useRef<Form>(null);
    const options = props.options;

    const getEditorType = (field: Field) => {
        switch (field.type) {
            case FieldType.TEXT:
                return 'dxTextBox';
            case FieldType.NUMBER:
                return 'dxNumberBox';
            case FieldType.DATE:
                return 'dxDateBox';
            case FieldType.SELECT:
                return 'dxSelectBox';
            case FieldType.CHECKBOX:
                return 'dxCheckBox';
            case FieldType.RADIO:
                return 'dxRadioGroup';
            case FieldType.TEXTAREA:
                return 'dxTextArea';
            case FieldType.PASSWORD:
                return 'dxTextBox';
            case FieldType.FILE:
                return 'dxFileUploader';
            case FieldType.SWITCH:
                return 'dxSwitch';
        }
    }

    const fields = options.fields.map((field: Field, index) => {
        if (field.type === FieldType.NONE) return null;
        return <SimpleItem
            key={index}
            dataField={field.name}
            editorType={getEditorType(field)}
            label={{text: field.label}}
            isRequired={field.required}
        />
    });

    bus.on(options.events.EDIT, (payload: any) => {
        formRef.current?.instance.option('formData', payload.row);
    });

    return (
        <div>
            <Form ref={formRef}>
                {fields}
            </Form>
        </div>
    );
}
