import "./OutboundPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {OutboundDetailFields, OutboundFields} from "./OutboundFields";
import {useEffect, useRef} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {useEvents} from "../../core/event/EventHook";

export const OutboundPage = () => {
    const [on, emit] = useEvents();
    const container = "wrapper";
    const fields = OutboundFields();
    const detailFields = OutboundDetailFields();
    const formRef = useRef<DxFormEvents>(null)

    const API_URL = Constants.API_BASE_URL + 'movements';

    const EVENTS = {
        ADD: 'outbound:add',
        EDIT: 'outbound:edit',
        DELETE: 'outbound:delete',
        SAVE: 'outbound:save',
        CANCEL: 'outbound:cancel',
        SUCCESS: 'outbound:success',
        LOADING: 'outbound:loading',
    }

    let alreadySetup = false;

    const setupObservers = () => {
        on(EVENTS.EDIT, (payload: any) => {
            httpClient.get<any>(`${API_URL}/${payload.row.id}/items`).subscribe((response) => {
                formRef.current?.setDetailData(response.data);
            })
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
                id={'outbound'}
                title={'Salidas'}
                url={API_URL + '?type=outbound'}
                detailUrl={API_URL + '/{id}/items'}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                detailFields={detailFields}
                events={EVENTS}
            />
            <DxDialog
                width={800}
                height={450}
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
