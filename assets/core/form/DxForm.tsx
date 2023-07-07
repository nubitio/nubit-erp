import Form, {GroupItem, Item, SimpleItem} from "devextreme-react/form";
import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {useEffect, useRef} from "react";
import {dxUtils} from "../common/DxUtils";
import {bus} from "../event/EventBus";
import {httpClient} from "../http/HttpClient";
import {StorageUtils} from "../utils/StorageUtils";
import {confirm} from "devextreme/ui/dialog";
import DataGrid, {Editing} from "devextreme-react/data-grid";
import {buildColumns} from "../datagrid/DxDataGridUtils";
import ArrayStore from "devextreme/data/array_store";
import DataSource from "devextreme/data/data_source";

export const DxForm = ({options}: any) => {
    const formRef = useRef<Form>(null);
    const gridRef = useRef<DataGrid>(null);
    const uploadedFiles = useRef<any[]>([]);
    const isEdit = useRef<boolean>(false);
    const data = useRef<DataSource>(new DataSource({
        store: new ArrayStore({
            data: [],
        }),
    }));


    const getEditorType = (field: Field): any => {
        switch (field.type) {
            case FieldType.TEXT:
                return 'dxTextBox';
            case FieldType.NUMBER:
            case FieldType.CURRENCY:
                return 'dxNumberBox';
            case FieldType.DATE:
                return 'dxDateBox';
            case FieldType.SELECT:
            case FieldType.ENUM:
            case FieldType.ENTITY:
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

    const getIdField = () => {
        return options.fields.find(field => field.isIdentity)?.name;
    }

    const fields = options.fields.map((field: Field, index) => {
        if (field.type === FieldType.NONE || field.hidden) return null;
        let editorOptions: any = {};
        if (field.type === FieldType.ENTITY) {
            editorOptions = {
                dataSource: {
                    store: dxUtils.getDataStore(field.url, field.valueField, field.byKeyUrl),
                    paginate: true,
                    pageSize: 10
                },
                valueExpr: field.valueField,
                displayExpr: field.textField,
                searchEnabled: field.mode === 'remote',
                wrapItemText: true,
            }
        }
        if (field.type === FieldType.ENUM) {
            editorOptions = {
                dataSource: field.data,
                valueExpr: 'value',
                displayExpr: 'text'
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
        if (field.type === FieldType.DATE) {
            editorOptions = {
                type: 'date',
                displayFormat: 'dd/MM/yyyy',
                defaultValue: new Date()
            }
        }
        editorOptions = {
            ...editorOptions,
            readOnly: field.readonly,
            disabled: field.disabled,
            onFocusIn: field.onClick,
            onValueChanged: field.onChange
        };

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
            let store = data.current?.store() as ArrayStore;
            store.clear();
            data.current?.reload();
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
                        if (typeof formData[field.name] === 'object' && formData[field.name] !== null) {
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
                    if (field.type === FieldType.DATE) {
                        formData[field.name] = formData[field.name]?.toISOString();
                    }
                });

                if (options.detailFields) {
                    formData['details'] = gridRef.current?.instance.getDataSource().items();
                }

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

    const renderForm = () => {
        if (options.detailFields) {
            return <Form ref={formRef}>
                <Item itemType="group" colCount={3}>
                    <GroupItem>
                        {fields}
                    </GroupItem>
                    <GroupItem colSpan={2}>
                        <DataGrid
                            ref={gridRef}
                            dataSource={data.current}
                            showBorders={true}
                            showRowLines={true}
                            showColumnLines={true}
                            onRowInserted={(e: any) => {
                                e.component.saveEditData();
                            }}
                            onRowRemoved={(e: any) => {
                                e.component.saveEditData();
                            }}
                            onRowUpdated={(e: any) => {
                                e.component.saveEditData();
                            }}
                        >
                            <Editing
                                mode="cell"
                                allowUpdating={true}
                                allowDeleting={true}
                                allowAdding={true}
                                confirmDelete={false}
                            />
                            {buildColumns(options.detailFields, true)}
                        </DataGrid>
                    </GroupItem>
                </Item>
            </Form>
        } else {
            return <Form ref={formRef}>
                {fields}
            </Form>
        }
    }

    return (
        <div>
            {renderForm()}
        </div>
    );
}
