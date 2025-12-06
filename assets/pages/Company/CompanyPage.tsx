import "./CompanyPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, FORM_EVENTS} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {CompanyFields} from "./CompanyFields";
import {useEffect, useRef} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {useEvents} from "../../core/event/EventHook";

export const CompanyPage = () => {
    const container = "wrapper";
    const formRef = useRef<any>(null);
    const fields = CompanyFields(formRef.current);
    const [on, emit] = useEvents();

    const API_URL = Constants.API_BASE_URL + 'companies';

    const EVENTS = {
        ADD: 'company:add',
        EDIT: 'company:edit',
        DELETE: 'company:delete',
        SAVE: 'company:save',
        CANCEL: 'company:cancel',
        SUCCESS: 'company:success',
        LOADING: 'company:loading',
    }

    useEffect(() => {
        const sub = on<any>(FORM_EVENTS.FIELD_CHANGED, (data) => {
            if (data.field !== 'ubigeo') {
                return;
            }

            if (!data.value) {
                formRef.current?.setFieldValue('ubigeoCode', '');
                formRef.current?.setFieldValue('department', '');
                formRef.current?.setFieldValue('province', '');
                formRef.current?.setFieldValue('district', '');
                return;
            }

            emit(EVENTS.LOADING, true);
            httpClient.get<any>(`${Constants.API_BASE_URL}ubigeos/${data.value}`).subscribe({
                next: (response) => {
                    const ubigeo = response.data;
                    formRef.current?.setFieldValue('ubigeoCode', ubigeo.code ?? '');
                    formRef.current?.setFieldValue('department', ubigeo.department ?? '');
                    formRef.current?.setFieldValue('province', ubigeo.province ?? '');
                    formRef.current?.setFieldValue('district', ubigeo.district ?? '');
                },
                error: () => {
                    formRef.current?.setFieldValue('ubigeoCode', '');
                    formRef.current?.setFieldValue('department', '');
                    formRef.current?.setFieldValue('province', '');
                    formRef.current?.setFieldValue('district', '');
                }
            }).add(() => emit(EVENTS.LOADING, false));
        });

        return () => sub?.unsubscribe();
    }, [on, emit]);

    return (
        <div id={container}>
            <DxDataGrid
                id={'company'}
                title={'Empresas'}
                url={API_URL}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                events={EVENTS}
            />
            <DxDialog
                events={EVENTS}
            >
                <DxForm
                    url={API_URL}
                    fields={fields}
                    events={EVENTS}
                    useIRI={true}
                    ref={formRef}
                />
            </DxDialog>
        </div>
    );
};
