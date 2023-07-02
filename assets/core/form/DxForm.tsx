import Form, {SimpleItem} from "devextreme-react/form";
import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {useEffect, useRef} from "react";
import {dxUtils} from "../common/DxUtils";
import {bus} from "../event/EventBus";
import {httpClient} from "../http/HttpClient";
import {StorageUtils} from "../utils/StorageUtils";

export const DxForm = (props: any) => {
    const formRef = useRef<Form>(null);
    const options = props.options;

    let files: any = [];

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
            case FieldType.ENTITY:
                return 'dxSelectBox';
        }
    }

    const fields = options.fields.map((field: Field, index) => {
        if (field.type === FieldType.NONE) return null;
        let editorOptions: any = {};
        if (field.type === FieldType.ENTITY) {
            editorOptions = {
                dataSource: {
                    store: dxUtils.getDataStore(field.url, field.valueField),
                    paginate: true,
                    pageSize: 10
                },
                valueExpr: field.valueField,
                displayExpr: field.textField
            }
        }
        if (field.type === FieldType.FILE) {
            editorOptions = {
                accept: field.accept,
                uploadUrl: field.url,
                name: 'file',
                onBeforeSend: (e: any) => {
                    let token = StorageUtils.getItem('token');
                    e.request.setRequestHeader('Authorization', `Bearer ${token}`)
                },
                onUploaded: (e: any) => {
                    let response = JSON.parse(e.request.response);
                    files.push({field: field.name, value: response.id});
                }
            }
        }
        return <SimpleItem
            key={index}
            dataField={field.name}
            editorType={getEditorType(field)}
            label={{text: field.label}}
            isRequired={field.required}
            editorOptions={editorOptions}
        />
    });

    const setupObservers = () => {
        bus.on(options.events.ADD, (payload: any) => {
            formRef.current?.instance.resetValues();
            options.fields.forEach((field: Field) => {
                if (field.defaultValue) {
                    formRef.current?.instance.getEditor(field.name)?.option('value', field.defaultValue);
                }
            });
        });

        bus.on(options.events.EDIT, (payload: any) => {
            formRef.current?.instance.option('formData', payload.row);
        });

        bus.on(options.events.SAVE, () => {
            let result = formRef.current?.instance.validate()
            if (result?.isValid) {
                let formData = formRef.current?.instance.option('formData');
                console.log(files)
                files.forEach((file: any) => {
                    console.log(formData[file.field])
                    console.log(file.value)
                    formData[file.field] = {
                        id: file.value
                    }
                });
                options.fields.forEach((field: Field) => {
                    if (field.type === FieldType.ENTITY) {
                        formData[field.name] = {
                            [field.valueField]: formData[field.name]
                        }
                    }
                });
                httpClient.post(options.url, formData).subscribe((response: any) => {
                    bus.emit(options.events.SUCCESS);
                });
            }
        });
    }

    let alreadySetup = false;

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

    return (
        <div>
            <Form ref={formRef}>
                {fields}
            </Form>
        </div>
    );
}
