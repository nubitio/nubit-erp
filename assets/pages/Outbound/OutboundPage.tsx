import "./OutboundPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {OutboundFields, OutboundDetailFields} from "./OutboundFields";

export const OutboundPage = () => {
    const container = "wrapper";
    const fields = OutboundFields();
    const detailFields = OutboundDetailFields();

    const API_URL = Constants.API_BASE_URL + 'sales';

    const EVENTS = {
        ADD: 'outbound:add',
        EDIT: 'outbound:edit',
        DELETE: 'outbound:delete',
        SAVE: 'outbound:save',
        CANCEL: 'outbound:cancel',
        SUCCESS: 'outbound:success',
        LOADING: 'outbound:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                    title={'Outbound'}
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
                    width={900}
                    height={620}
                    events={EVENTS}
            >
                <DxForm
                        url={API_URL}
                        fields={fields}
                        detailFields={detailFields}
                        events={EVENTS}
                />
            </DxDialog>
        </div>
    );
};
