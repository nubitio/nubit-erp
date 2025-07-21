import './DxForm.scss';
import Form, { FormRef, GroupItem, Item } from 'devextreme-react/form';
import { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import React, { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import { FORM_ERRORS_EVENT, httpClient } from '../http/HttpClient';
import { confirm } from 'devextreme/ui/dialog';
import DataGrid, {
    DataGridRef,
    Editing,
    KeyboardNavigation,
    LoadPanel,
    Paging,
} from 'devextreme-react/data-grid';
import { buildColumns } from '../datagrid/DxDataGridUtils';
import ArrayStore from 'devextreme/data/array_store';
import DataSource from 'devextreme/data/data_source';
import { useEvents } from '../event/EventHook';
import { buildField } from './FormUtils';
import { DateUtils } from '../utils/DateUtils';
import toast, { getIRI } from '../utils/SharedUtils';

export interface DxFormOptions {
    className?: string;
    url: string;
    detailUrl?: string;
    fields: Field[];
    detailFields?: any;
    detailPropertyName?: string;
    allowAdding?: boolean;
    allowUpdating?: boolean;
    allowDeleting?: boolean;
    events?: any;
    editable?: boolean;
    format?: 'json' | 'multipart';
    requiredDetail?: boolean;
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
    enableValidation: (field: string) => void;
    disableValidation: (field: string) => void;
    setError: (field: string, message: string) => void;
    showField: (field: string) => void;
    hideField: (field: string) => void;
    validate: () => boolean;
    setIsEdit: (value: boolean) => void;
}

export const FORM_EVENTS = {
    FIELD_CHANGED: 'on:field:changed',
    ROW_ADDED: 'on:row:added',
    ROW_REMOVED: 'on:row:removed',
    ROW_UPDATED: 'on:row:updated',
    LOADING: 'on:loading',
    LOADING_DETAIL: 'on:loading:detail',
};

export type UploadedFile = {
    name: string;
    iri: string;
};

export const DxForm = forwardRef<DxFormEvents, DxFormOptions>((options, ref) => {
    const formRef = useRef<FormRef | null>(null);
    const gridRef = useRef<DataGridRef | null>(null);
    const uploadedFiles = useRef<UploadedFile[]>([]);
    const isEdit = useRef<boolean>(false);
    const data = useRef<DataSource>(
        new DataSource({
            store: new ArrayStore({
                key: options.detailFields?.find((field) => field.isIdentity)?.name ?? '',
                data: [],
            }),
        }),
    );
    const [on, emit] = useEvents();
    const detailPropertyName = options.detailPropertyName ?? 'items';

    const getIdField = () => {
        return options.fields.find((field) => field.isIdentity)?.name ?? '';
    };

    const getDetailIdField = () => {
        return options.detailFields?.find((field) => field.isIdentity)?.name ?? '';
    };

    const buildFields = (fields: Field[]) => {
        return fields.map((field: Field, index) => {
            return buildField(field, index, options.editable ?? true, uploadedFiles, formRef);
        });
    };

    const buildEmptyRow = (fields: Field[]) => {
        const row: any = {};
        fields.forEach((field: Field) => {
            if (field.defaultValue !== undefined) {
                row[field.name] = field.defaultValue;
            } else {
                row[field.name] = undefined;
            }
        });
        return row;
    };

    const validate = () => {
        const formValidation = formRef.current?.instance().validate();
        const formValid = formValidation?.isValid ?? true;
        let gridValid = !gridRef.current?.instance().hasEditData();

        if (options.requiredDetail) {
            gridValid = (gridRef.current?.instance().totalCount() ?? 0) > 0;
        }

        if (!formValid || !gridValid) {
            toast('Uno o más campos del formulario no son válidos', 'error');
        }

        return formValid && gridValid;
    };

    const getData = (data: any) => {
        const row = { ...data };
        options.fields.forEach((field: Field) => {
            if (field.type === FieldType.FILE) {
                delete row[field.name];
            }
            if (field.type === FieldType.PASSWORD) {
                row[field.name] = '';
            }
            if (field.type === FieldType.DATE) {
                const type = typeof row[field.name];
                if (type === 'string') {
                    row[field.name] = DateUtils.dateParser(row[field.name]);
                } else if (type === 'object') {
                    row[field.name] = new Date(row[field.name]);
                } else {
                    row[field.name] = null;
                }
            }
            if (field.type === FieldType.ENTITY) {
                const type = typeof row[field.name];
                if (type === 'string') {
                    const value = row[field.name].split('/').pop();
                    row[field.name] = { id: value };
                }
                if (row[field.name] === null || row[field.name] === undefined) {
                    row[field.name] = field.loadOptions[0]?.prependData[0] ?? null;
                }
            }
        });

        return row;
    };

    const setupObservers = () => {
        on(FORM_ERRORS_EVENT, (errors: any) => {
            toast('Uno o más campos del formulario no son válidos', 'error');
            errors.forEach((error: any) => {
                setError(error.propertyPath, error.message);
            });
        });

        on<boolean>(FORM_EVENTS.LOADING, (loading) => {
            if (loading) {
                gridRef.current?.instance().option('noDataText', '');
                gridRef.current?.instance().beginCustomLoading('Cargando...');
            } else {
                gridRef.current?.instance().endCustomLoading();
            }
        });

        on(options.events?.ADD, (payload: any) => {
            formRef.current?.instance().option('formData', null);
            uploadedFiles.current = [];
            const store = data.current?.store() as ArrayStore;
            store.clear();
            data.current?.reload();
            if (payload) {
                options.fields.forEach((field: Field) => {
                    if (payload[field.name] === undefined) return;
                    const row = getData(payload);
                    formRef.current?.instance().getEditor(field.name)?.option('value', row[field.name]);
                });
                if (payload[detailPropertyName]) {
                    setDetailData(payload[detailPropertyName]);
                }
            } else {
                options.fields.forEach((field: Field) => {
                    if (field.defaultValue !== undefined) {
                        if (field.defaultValue instanceof Function) {
                            formRef.current
                                ?.instance()
                                .getEditor(field.name)
                                ?.option('value', field.defaultValue());
                        } else {
                            formRef.current
                                ?.instance()
                                .getEditor(field.name)
                                ?.option('value', field.defaultValue);
                        }
                    }
                });
            }
            isEdit.current = false;
        });

        on(options.events?.EDIT, (payload: any) => {
            const row = getData(payload.row);
            formRef.current?.instance().option('formData', row);
            uploadedFiles.current = [];
            isEdit.current = true;
            const detailUrl = options.detailUrl?.replace('{id}', row[getIdField()]);
            if (!detailUrl) return;
            setDetailData([]);
            emit(FORM_EVENTS.LOADING, true);
            httpClient
                .get<any>(detailUrl)
                .subscribe((response) => {
                    setDetailData(response.data);
                })
                .add(() => emit(FORM_EVENTS.LOADING, false));
        });

        on(options.events?.DELETE, (payload: any) => {
            const result = confirm('¿Está seguro de eliminar el registro?', 'Confirmar eliminación');
            result.then((dialogResult) => {
                if (dialogResult) {
                    emit(options.events?.LOADING, true);
                    httpClient
                        .delete(`${options.url}/${payload.row[getIdField()]}`)
                        .subscribe((response: any) => {
                            emit(options.events?.SUCCESS, response);
                        })
                        .add(() => emit(options.events?.LOADING, false));
                }
            });
        });

        on(options.events?.SAVE, () => {
            if (!validate()) return;
            emit(options.events?.LOADING, true);
            const _formData = formRef.current?.instance().option('formData');
            let formData = { ..._formData };
            console.log(uploadedFiles.current);
            uploadedFiles.current.forEach((file) => {
                formData[file.name] = file.iri;
            });
            options.fields.forEach((field: Field) => {
                if (field.type === FieldType.ENTITY || field.type === FieldType.TAGS) {
                    const type = typeof formData[field.name];
                    if (
                        formData[field.name] === null ||
                        formData[field.name] === undefined ||
                        formData[field.name] === '' ||
                        formData[field.name] === -999
                    ) {
                        delete formData[field.name];
                        return;
                    }
                    if (type === 'object' && formData[field.name] !== null && !field.multiple) {
                        formData[field.name] = getIRI(
                            field.url ?? '',
                            formData[field.name][field.valueField].toString(),
                        );
                    }

                    if (type === 'string' || type === 'number') {
                        formData[field.name] = getIRI(field.url ?? '', formData[field.name].toString());
                    }

                    if (field.multiple) {
                        if (Array.isArray(formData[field.name])) {
                            formData[field.name] = formData[field.name].map((item: any) => {
                                if (typeof item === 'object') {
                                    return getIRI(field.url ?? '', item[field.valueField].toString());
                                }
                                return getIRI(field.url ?? '', item.toString());
                            });
                        } else {
                            formData[field.name] = [getIRI(field.url ?? '', formData[field.name].toString())];
                        }
                    }
                }
                if (field.type === FieldType.FILE) {
                    if (options.format === 'multipart') {
                        const files = getFieldValue(field.name);
                        if (files.length > 0) {
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
                    const date = new Date(formData[field.name]);
                    const timeOffset = date.getTimezoneOffset() * 60000;
                    date.setTime(date.getTime() - timeOffset);
                    formData[field.name] = date.toISOString();
                }
                if (field.type === FieldType.TEXT && field.valueType === 'number') {
                    formData[field.name] = Number(formData[field.name]);
                }
            });

            if (options.detailFields) {
                const items = gridRef.current?.instance().getDataSource().items() ?? [];
                formData[detailPropertyName] = JSON.parse(JSON.stringify(items));
                options.detailFields.forEach((field: Field) => {
                    if (field.type === FieldType.ENTITY) {
                        formData[detailPropertyName].forEach((detail: any) => {
                            const type = typeof (detail[field.name] ?? '');
                            if (type === 'object') {
                                detail[field.name] = getIRI(
                                    field.url ?? '',
                                    detail[field.name][field.valueField].toString(),
                                );
                            }
                        });
                    }
                    if (field.type === FieldType.NUMBER || field.type === FieldType.CURRENCY) {
                        formData[detailPropertyName].forEach((detail: any) => {
                            detail[field.name] = Number(detail[field.name]);
                        });
                    }
                    if (!isEdit.current) {
                        formData[detailPropertyName].forEach((detail: any) => {
                            delete detail[getDetailIdField()];
                        });
                    } else {
                        formData[detailPropertyName].forEach((detail: any) => {
                            if (typeof detail[getDetailIdField()] === 'string') {
                                delete detail[getDetailIdField()];
                            }
                        });
                    }
                });
            }

            const id = formData[getIdField()];

            if (options.format === 'multipart') {
                formData = getMultipartFormData(formData);
            }

            const hasId = formData[getIdField()] !== undefined;

            if (isEdit.current || hasId) {
                httpClient
                    .patch(`${options.url}/${id}`, formData)
                    .subscribe((response: any) => {
                        emit(options.events?.SUCCESS, response);
                    })
                    .add(() => emit(options.events?.LOADING, false));
            } else {
                httpClient
                    .post(options.url, formData)
                    .subscribe((response: any) => {
                        emit(options.events?.SUCCESS, response);
                    })
                    .add(() => emit(options.events?.LOADING, false));
            }
        });
    };

    const getMultipartFormData = (formData: any) => {
        const form = new FormData();
        for (const key in formData) {
            form.append(key, formData[key]);
        }
        return form;
    };

    useEffect(() => {
        setupObservers();
    }, []);

    const getFormData = () => {
        return formRef.current?.instance().option('formData');
    };

    const getFieldValue = (field: string) => {
        return formRef.current?.instance().getEditor(field)?.option('value');
    };

    const setFieldValue = (field: string, value: any) => {
        formRef.current?.instance().getEditor(field)?.option('value', value);
    };

    const setReadonly = (field: string, value: boolean) => {
        formRef.current?.instance().getEditor(field)?.option('readOnly', value);
    };

    const setDisabled = (field: string, value: boolean) => {
        formRef.current?.instance().getEditor(field)?.option('disabled', value);
    };

    const getDetailData = () => {
        return gridRef.current?.instance().getDataSource().items();
    };

    const getValues = () => {
        return formRef.current?.instance().option('formData');
    };

    const setValues = (data: any) => {
        const row = getData(data);
        formRef.current?.instance().option('formData', row);
    };

    const setDetailData = (data: any[]) => {
        const store = gridRef.current?.instance().getDataSource().store() as ArrayStore;
        store.clear();
        data.forEach((row: any) => {
            store.insert(row);
        });
        reloadDetailData();
    };

    const reloadDetailData = () => {
        gridRef.current?.instance().getDataSource().reload();
    };

    const setError = (field: string, message: string) => {
        if (field === 'items') {
            toast(message, 'error');
            return;
        }
        const editor = formRef.current?.instance().getEditor(field);
        editor?.option({
            isValid: false,
            validationError: {
                message: message,
            },
        });
    };

    const showField = (field: string) => {
        formRef.current?.instance().itemOption(field, 'visible', true);
    };

    const hideField = (field: string) => {
        formRef.current?.instance().itemOption(field, 'visible', false);
    };

    const enableValidation = (field: string) => {
        formRef.current?.instance().getEditor(field)?.option('isValid', true);
    };

    const disableValidation = (field: string) => {
        formRef.current?.instance().getEditor(field)?.option('isValid', false);
    };

    const setIsEdit = (value: boolean) => {
        isEdit.current = value;
    };

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
        setError,
        showField,
        hideField,
        enableValidation,
        disableValidation,
        setIsEdit,
    }));

    const renderForm = () => {
        if (options.detailFields?.length > 0) {
            return (
                <Form ref={formRef} className={options.className} labelMode={'floating'}>
                    <Item itemType="group" colCount={4}>
                        <GroupItem>{buildFields(options.fields)}</GroupItem>
                        <GroupItem colSpan={3}>
                            <DataGrid
                                id={'form-detail-grid'}
                                ref={gridRef}
                                keyExpr={getDetailIdField()}
                                dataSource={data.current}
                                showBorders={true}
                                showRowLines={true}
                                showColumnLines={true}
                                focusedRowEnabled={true}
                                repaintChangesOnly={true}
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
                                <LoadPanel showPane={false} />
                                <KeyboardNavigation
                                    editOnKeyPress={true}
                                    enterKeyAction="moveFocus"
                                    enterKeyDirection="column"
                                />
                                <Editing
                                    mode="cell"
                                    allowUpdating={options.allowUpdating ?? true}
                                    allowDeleting={options.allowDeleting ?? true}
                                    allowAdding={options.allowAdding ?? true}
                                    startEditAction="dblClick"
                                    confirmDelete={false}
                                />
                                <Paging enabled={false} />
                                {buildColumns(options.detailFields, true)}
                            </DataGrid>
                        </GroupItem>
                    </Item>
                </Form>
            );
        } else {
            return (
                <Form ref={formRef} className={options.className} labelMode={'floating'}>
                    <GroupItem>{buildFields(options.fields)}</GroupItem>
                </Form>
            );
        }
    };

    return <React.Fragment>{renderForm()}</React.Fragment>;
});
