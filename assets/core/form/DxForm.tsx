import Form, {SimpleItem} from "devextreme-react/form";
import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {useEffect, useRef} from "react";
import {dxUtils} from "../common/DxUtils";
import {bus} from "../event/EventBus";
import {httpClient} from "../http/HttpClient";
import {StorageUtils} from "../utils/StorageUtils";
import {confirm} from "devextreme/ui/dialog";

export const DxForm = (props: any) => {
    const formRef = useRef<Form>(null);
    const uploadedFiles = useRef<any[]>([]);
    const isEdit = useRef<boolean>(false);
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
            case FieldType.ENTITY:
                return 'dxSelectBox';
        }
    }

    const getIdField = () => {
        return options.fields.find(field => field.isIdentity)?.name;
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
                    uploadedFiles.current.push({field: field.name, value: response.id});
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
            isEdit.current = false;
        });

        bus.on(options.events.EDIT, (payload: any) => {
            let row = {...payload.row};
            options.fields.forEach((field: Field) => {
                if (field.type === FieldType.FILE) {
                    delete (row[field.name]);
                }
            });
            formRef.current?.instance.option('formData', row);
            isEdit.current = true;
        });

        bus.on(options.events.DELETE, (payload: any) => {
            let result = confirm('¿Está seguro de eliminar el registro?', 'Confirmar eliminación');
            result.then((dialogResult) => {
                if (dialogResult) {
                    httpClient.delete(`${options.url}/${payload.row[getIdField()]}`).subscribe((response: any) => {
                        bus.emit(options.events.SUCCESS, response);
                    });
                }
            });
        });

        bus.on(options.events.SAVE, () => {
            let result = formRef.current?.instance.validate()
            if (result?.isValid) {
                let formData = formRef.current?.instance.option('formData');
                uploadedFiles.current.forEach((file: any) => {
                    formData[file.field] = {
                        id: file.value
                    }
                });
                options.fields.forEach((field: Field) => {
                    if (field.type === FieldType.ENTITY) {
                        if (formData[field.name] === null || formData[field.name] === undefined) {
                            delete formData[field.name];
                            return;
                        }
                        formData[field.name] = {
                            [field.valueField]: formData[field.name]
                        }
                    }
                    if (field.type === FieldType.FILE) {
                        if (Array.isArray(formData[field.name])) {
                            delete formData[field.name];
                            return;
                        }
                    }
                });

                if (isEdit.current) {
                    httpClient.put(`${options.url}/${formData[getIdField()]}`, formData).subscribe((response: any) => {
                        bus.emit(options.events.SUCCESS, response);
                    });
                } else {
                    httpClient.post(options.url, formData).subscribe((response: any) => {
                        bus.emit(options.events.SUCCESS, response);
                    });
                }
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
