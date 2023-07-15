import "./ProductionPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {useEffect, useRef} from "react";
import {bus} from "../../core/event/EventBus";
import {httpClient} from "../../core/http/HttpClient";
import {ProductionDetailFields, ProductionFields} from "./ProductionFields";

export const ProductionPage = () => {
    const formRef = useRef<DxFormEvents>(null)
    const container = "wrapper";
    const fields = ProductionFields(formRef.current);
    const detailFields = ProductionDetailFields();

    const API_URL = Constants.API_BASE_URL + 'productions';

    const EVENTS = {
        ADD: 'production:add',
        EDIT: 'production:edit',
        DELETE: 'production:delete',
        SAVE: 'production:save',
        CANCEL: 'production:cancel',
        SUCCESS: 'production:success',
        LOADING: 'production:loading',
    }

    let alreadySetup = false;

    const setupObservers = () => {
        bus.on(EVENTS.EDIT, (payload: any) => {
            httpClient.get<any>(`${API_URL}/${payload.row.id}/details`).subscribe((response) => {
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
                title={'Ordenes de producción'}
                url={API_URL}
                detailUrl={API_URL + '/{id}/details'}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                detailFields={detailFields}
                events={EVENTS}
            />
            <DxDialog
                width={800}
                height={500}
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
