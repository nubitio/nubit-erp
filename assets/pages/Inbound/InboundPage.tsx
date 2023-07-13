import "./InboundPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {InboundFields, InboundDetailFields} from "./InboundFields";

export const InboundPage = () => {
    const container = "wrapper";
    const fields = InboundFields();
    const detailFields = InboundDetailFields();

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

    return (
        <div id={container}>
            <DxDataGrid
                    title={'Inbound'}
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
