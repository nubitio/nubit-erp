import "./InboundPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {InboundDetailFields, InboundFields} from "./InboundFields";
import {useEffect, useRef} from "react";
import {bus} from "../../core/event/EventBus";
import {httpClient} from "../../core/http/HttpClient";

export const InboundPage = () => {
    const container = "wrapper";
    const fields = InboundFields();
    const detailFields = InboundDetailFields();
    const formRef = useRef<DxFormEvents>(null)

    const API_URL = Constants.API_BASE_URL + 'sales';

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

    const setupObservers = () => {
        bus.on(EVENTS.EDIT, (payload: any) => {
            httpClient.get<any>(`${API_URL}/${payload.row.movimiento_id}/details`).subscribe((response) => {
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
                title={'Ingresos'}
                url={API_URL + '?type=inbound'}
                detailUrl={API_URL + '/{id}/details'}
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
