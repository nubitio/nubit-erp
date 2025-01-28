import './DxForm.scss';
import Form, {FormRef, GroupItem, Item} from "devextreme-react/form";
import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import React, {forwardRef, useEffect, useImperativeHandle, useRef} from "react";
import {FORM_ERRORS_EVENT, httpClient} from "../http/HttpClient";
import {confirm} from "devextreme/ui/dialog";
import DataGrid, {DataGridRef, Editing, KeyboardNavigation, Paging} from "devextreme-react/data-grid";
import {buildColumns} from "../datagrid/DxDataGridUtils";
import ArrayStore from "devextreme/data/array_store";
import DataSource from "devextreme/data/data_source";
import {useEvents} from "../event/EventHook";
import {buildField} from "./FormUtils";
import {DateUtils} from "../utils/DateUtils";
import toast from "../utils/SharedUtils";

export interface DxFormOptions {
    className?: string;
    url: string;
    fields: Field[];
    detailFields?: Field[];
    allowAdding?: boolean;
    allowUpdating?: boolean;
    allowDeleting?: boolean;
    events?: any;
    editable?: boolean;
    format?: 'json' | 'multipart';
    useIRI?: boolean;
}

export interface DxFormEvents {
    getFormData: () => any;
    getFieldValue: (name: string) => any;
    setFieldValue: (name: string, value: any) => void;
    getDetailData: () => any;
    setDetailData: (data: any[]) => void;
    reloadDetailData: () => void;
    getValues: () => any;
    setValues: (data: any) => void;
    setReadonly: (field: string, value: boolean) => void;
    setDisabled: (field: string, value: boolean) => void;
    validate: () => boolean;
}

export const FORM_EVENTS = {
    FIELD_CHANGED: 'on:field:changed',
    ROW_ADDED: 'on:row:added',
    ROW_REMOVED: 'on:row:removed',
    ROW_UPDATED: 'on:row:updated',
    LOADING: 'on:loading',
    LOADING_DETAIL: 'on:loading:detail',
}

export const DxForm = forwardRef<DxFormEvents, DxFormOptions>((options, ref) => {
    const formRef = useRef<FormRef | null>(null);
    const gridRef = useRef<DataGridRef | null>(null);
    const uploadedFiles = useRef<any[]>([]);
    const isEdit = useRef<boolean>(false);
    const data = useRef<DataSource>(new DataSource({
        store: new ArrayStore({
            key: options.fields.find(field => field.isIdentity)?.name ?? '',
            data: [],
        }),
    }));
    const [on, emit] = useEvents();

    const getIdField = () => {
        return options.fields.find(field => field.isIdentity)?.name ?? '';
    }

    const buildFields = (fields: Field[]) => {
        return fields.map((field: Field, index) => {
            return buildField(field, index, options.editable, uploadedFiles, formRef);
        });
    }

    const buildEmptyRow = (fields: Field[]) => {
        let row: any = {};
        fields.forEach((field: Field) => {
            if (field.defaultValue !== undefined) {
                row[field.name] = field.defaultValue;
            } else {
                row[field.name] = null;
            }
        });
        return row;
    }

    const validate = () => {
        let formValidation = formRef.current?.instance().validate();
        let formValid = formValidation?.isValid ?? true;
        let gridValid = gridRef.current?.instance().hasEditData() ?? true;

        if (options.detailFields) {
            gridValid = (gridRef.current?.instance().totalCount() ?? 0) > 0;
        }

        if (!formValid || !gridValid) {
            toast('Uno o más campos del formulario no son válidos', 'error');
        }

        return formValid && gridValid;
    }

    const getIRI = (url: string, value: string) => {
        if (value.startsWith('/')) {
            return value;
        }

        return url + '/' + value;
    }

    const getData = (data: any) => {
        let row = {...data};
        options.fields.forEach((field: Field) => {
            if (field.type === FieldType.FILE) {
                delete (row[field.name]);
            }
            if (field.type === FieldType.PASSWORD) {
                row[field.name] = '';
            }
            if (field.type === FieldType.DATE) {
                let type = typeof row[field.name];
                if (type === 'string') {
                    row[field.name] = DateUtils.dateParser(row[field.name]);
                } else if (type === 'object') {
                    row[field.name] = new Date(row[field.name]);
                } else {
                    row[field.name] = null;
                }
            }
            if (field.type === FieldType.ENTITY) {
                let type = typeof row[field.name];
                if (type === 'string') {
                    let value = row[field.name].split('/').pop();
                    row[field.name] = {id: value};
                }
            }
        });

        return row;
    }

    const setupObservers = () => {
        on(FORM_ERRORS_EVENT, (errors: any) => {
            toast('Uno o más campos del formulario no son válidos', 'error');
            errors.forEach((error: any) => {
                setError(error.propertyPath, error.message);
            });
        });

        on(options.events?.ADD, (payload: any) => {
            formRef.current?.instance().option('formData', null);
            uploadedFiles.current = [];
            let store = data.current?.store() as ArrayStore;
            store.clear();
            data.current?.reload();
            if (payload) {
                options.fields.forEach((field: Field) => {
                    if (payload[field.name] === undefined) return;
                    let row = getData(payload);
                    formRef.current?.instance().getEditor(field.name)?.option('value', row[field.name]);
                });
                if (payload['details']) {
                    setDetailData(payload['details'])
                }
            } else {
                options.fields.forEach((field: Field) => {
                    if (field.defaultValue !== undefined) {
                        formRef.current?.instance().getEditor(field.name)?.option('value', field.defaultValue);
                    }
                });
            }
            isEdit.current = false;
        });

        on(options.events?.EDIT, (payload: any) => {
            let row = getData(payload.row)
            formRef.current?.instance().option('formData', row);
            isEdit.current = true;
        });

        on(options.events?.DELETE, (payload: any) => {
            let result = confirm('¿Está seguro de eliminar el registro?', 'Confirmar eliminación');
            result.then((dialogResult) => {
                if (dialogResult) {
                    emit(options.events?.LOADING, true);
                    httpClient.delete(`${options.url}/${payload.row[getIdField()]}`).subscribe((response: any) => {
                        emit(options.events?.SUCCESS, response);
                    }).add(() => emit(options.events?.LOADING, false));
                }
            });
        })

        on(options.events?.SAVE, () => {
            if (!validate()) return;
            emit(options.events?.LOADING, true);
            let formData = formRef.current?.instance().option('formData');
            uploadedFiles.current.forEach((file: any) => {
                formData[file.field] = {
                    id: file.value
                }
            });
            options.fields.forEach((field: Field) => {
                if (field.type === FieldType.ENTITY) {
                    const type = typeof formData[field.name];
                    if (formData[field.name] === null || formData[field.name] === undefined) {
                        delete formData[field.name];
                        return;
                    }
                    if (type === 'object' && formData[field.name] !== null) {
                        if (options.useIRI ?? false) {
                            formData[field.name] = getIRI(field.url ?? '', formData[field.name][field.valueField].toString())
                        }
                    }

                    if (type === 'string' || type === 'number') {
                        if (options.useIRI ?? false) {
                            formData[field.name] = getIRI(field.url ?? '', formData[field.name].toString())
                        } else {
                            formData[field.name] = {
                                [field.valueField]: formData[field.name]
                            }
                        }
                    }
                }
                if (field.type === FieldType.FILE) {
                    if (options.format === 'multipart') {
                        const files = getFieldValue(field.name);
                        if (files) {
                            formData[field.name] = files[0];
                        } else {
                            delete formData[field.name];
                        }
                        return;
                    }
                    if (Array.isArray(formData[field.name])) {
                        delete formData[field.name];
                        return;
                    }
                }
                if (field.type === FieldType.NONE) {
                    if (!field.isIdentity) {
                        delete formData[field.name];
                        return;
                    }

                }
                if (field.type === FieldType.DATE) {
                    if (formData[field.name] === null || formData[field.name] === undefined) {
                        delete formData[field.name];
                        return;
                    }
                    let date = new Date(formData[field.name]);
                    let timeOffset = date.getTimezoneOffset() * 60000;
                    date.setTime(date.getTime() - timeOffset);
                    formData[field.name] = date.toISOString();
                }
                if (field.type === FieldType.TEXT && field.valueType === 'number') {
                    formData[field.name] = Number(formData[field.name]);
                }
            });

            if (options.detailFields) {
                formData['details'] = gridRef.current?.instance().getDataSource().items();
                options.detailFields.forEach((field: Field) => {
                    if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
                        formData['details'].forEach((detail: any) => {
                            detail[field.name] = Number(detail[field.name]);
                        });
                    }
                })
            }

            const id = formData[getIdField()];

            if (options.format === 'multipart') {
                formData = getMultipartFormData(formData);
            }

            if (isEdit.current) {
                httpClient.put(`${options.url}/${id}`, formData).subscribe((response: any) => {
                    emit(options.events?.SUCCESS, response);
                }).add(() => emit(options.events?.LOADING, false));
            } else {
                httpClient.post(options.url, formData).subscribe((response: any) => {
                    emit(options.events?.SUCCESS, response);
                }).add(() => emit(options.events?.LOADING, false));
            }
        });
    }

    const getMultipartFormData = (formData: any) => {
        let form = new FormData();
        for (let key in formData) {
            form.append(key, formData[key]);
        }
        return form;
    }

    useEffect(() => {
        setupObservers();
    }, []);

    const getFormData = () => {
        return formRef.current?.instance().option('formData');
    }

    const getFieldValue = (field: string) => {
        return formRef.current?.instance().getEditor(field)?.option('value');
    }

    const setFieldValue = (field: string, value: any) => {
        formRef.current?.instance().getEditor(field)?.option('value', value);
    }

    const setReadonly = (field: string, value: boolean) => {
        formRef.current?.instance().getEditor(field)?.option('readOnly', value);
    }

    const setDisabled = (field: string, value: boolean) => {
        formRef.current?.instance().getEditor(field)?.option('disabled', value);
    }

    const getDetailData = () => {
        return gridRef.current?.instance().getDataSource().items();
    }

    const getValues = () => {
        return formRef.current?.instance().option('formData');
    }

    const setValues = (data: any) => {
        let row = getData(data);
        formRef.current?.instance().option('formData', row);
    }

    const setDetailData = (data: any[]) => {
        let store = gridRef.current?.instance().getDataSource().store() as ArrayStore;
        store.clear();
        data.forEach((row: any) => {
            store.insert(row);
        });
        reloadDetailData();
    }

    const reloadDetailData = () => {
        gridRef.current?.instance().getDataSource().reload();
    }

    const setError = (field: string, message: string) => {
        const editor = formRef.current?.instance().getEditor(field);
        editor?.option({
            isValid: false,
            validationError: {
                message: message
            }
        });
    }

    useImperativeHandle(ref, () => ({
        getFormData,
        getFieldValue,
        setFieldValue,
        getDetailData,
        setDetailData,
        reloadDetailData,
        getValues,
        setValues,
        setReadonly,
        setDisabled,
        validate,
    }));

    const renderForm = () => {
        if (options.detailFields) {
            return (
                <Form
                    ref={formRef}
                    className={options.className}
                    labelMode={'floating'}
                >
                    <Item itemType="group" colCount={4}>
                        <GroupItem colCount={1}>
                            {buildFields(options.fields)}
                        </GroupItem>
                        <GroupItem colSpan={3}>
                            <DataGrid
                                ref={gridRef}
                                keyExpr={getIdField()}
                                dataSource={data.current}
                                showBorders={true}
                                showRowLines={true}
                                showColumnLines={true}
                                focusedRowEnabled={true}
                                onInitNewRow={(e: any) => {
                                    e.data = buildEmptyRow(options.detailFields ?? []);
                                    e.component.saveEditData();
                                }}
                                onRowInserted={(e: any) => {
                                    e.component.saveEditData();
                                    emit(FORM_EVENTS.ROW_ADDED, e.data);
                                }}
                                onRowRemoved={(e: any) => {
                                    e.component.saveEditData();
                                    emit(FORM_EVENTS.ROW_REMOVED, e.data);
                                }}
                                onRowUpdated={(e: any) => {
                                    e.component.saveEditData();
                                    emit(FORM_EVENTS.ROW_UPDATED, e.data);
                                }}
                            >
                                <KeyboardNavigation
                                    editOnKeyPress={true}
                                    enterKeyAction="moveFocus"
                                    enterKeyDirection="column"
                                />
                                <Editing
                                    mode="cell"
                                    allowUpdating={options.allowUpdating}
                                    allowDeleting={options.allowDeleting}
                                    allowAdding={options.allowAdding}
                                    startEditAction='click'
                                    confirmDelete={false}
                                />
                                <Paging enabled={false}/>
                                {buildColumns(options.detailFields, true)}
                            </DataGrid>
                        </GroupItem>
                    </Item>
                </Form>
            );
        } else {
            return (
                <Form
                    ref={formRef}
                    className={options.className}
                    labelMode={'floating'}
                    colCount={12}
                >
                    {buildFields(options.fields)}
                </Form>
            );
        }
    }

    return (
        <React.Fragment>
            {renderForm()}
        </React.Fragment>
    );
});

DxForm.defaultProps = {
    editable: true,
    allowAdding: true,
    allowUpdating: true,
    allowDeleting: true,
}
