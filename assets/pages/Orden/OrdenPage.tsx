import "./OrdenPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {OrdenFields, OrdenDetailFields} from "./OrdenFields";

export const OrdenPage = () => {
    const container = "wrapper";
    const fields = OrdenFields();
    const detailFields = OrdenDetailFields();

    const API_URL = Constants.API_BASE_URL + 'ordens';

    const EVENTS = {
        ADD: 'orden:add',
        EDIT: 'orden:edit',
        DELETE: 'orden:delete',
        SAVE: 'orden:save',
        CANCEL: 'orden:cancel',
        SUCCESS: 'orden:success',
        LOADING: 'orden:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                    title={'Orden'}
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
