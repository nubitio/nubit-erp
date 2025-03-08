import "./InboundPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents, FORM_EVENTS} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {InboundDetailFields, InboundFields} from "./InboundFields";
import {useEffect, useRef} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {useEvents} from "../../core/event/EventHook";

export const InboundPage = () => {
    const [on, emit] = useEvents();
    const formRef = useRef<DxFormEvents>(null)
    const container = "wrapper";
    const fields = InboundFields();
    const detailFields = InboundDetailFields();

    const API_URL = Constants.API_BASE_URL + 'movements';

    const EVENTS = {
        ADD: 'inbound:add',
        EDIT: 'inbound:edit',
        DELETE: 'inbound:delete',
        SAVE: 'inbound:save',
        CANCEL: 'inbound:cancel',
        SUCCESS: 'inbound:success',
        LOADING: 'inbound:loading',
    }

    let alreadySetup = false;

    const setTotal = () => {
        let rows = formRef.current?.getDetailData() ?? [];
        let total = 0;
        rows.forEach((row: any) => {
            total += row.precio_total;
        });

        formRef.current?.setFieldValue('total', Number(total));
    }

    const setupObservers = () => {
        on(EVENTS.EDIT, (payload: any) => {
            httpClient.get<any>(`${API_URL}/${payload.row.id}/items`).subscribe((response) => {
                formRef.current?.setDetailData(response.data);
            })
        })

        on(FORM_EVENTS.ROW_ADDED, () => {
            setTotal();
        })

        on(FORM_EVENTS.ROW_UPDATED, () => {
            setTotal();
        })

        on(FORM_EVENTS.ROW_REMOVED, () => {
            setTotal();
        })
    }

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

    return (
        <div id={container}>
            <DxDataGrid
                id={'inbound'}
                title={'Ingresos'}
                url={API_URL + '?type=inbound'}
                detailUrl={API_URL + '/{id}/items'}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                detailFields={detailFields}
                events={EVENTS}
            />
            <DxDialog
                width={900}
                height={600}
                events={EVENTS}
            >
                <DxForm
                    ref={formRef}
                    url={API_URL}
                    fields={fields}
                    detailFields={detailFields}
                    events={EVENTS}
                />
            </DxDialog>
        </div>
    );
};
