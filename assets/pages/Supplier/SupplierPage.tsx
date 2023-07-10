import "./SupplierPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {SupplierFields} from "./SupplierFields";

export const SupplierPage = () => {
    const container = "wrapper";
    const fields = SupplierFields();

    const API_URL = Constants.API_BASE_URL + 'suppliers';

    const EVENTS = {
        ADD: 'supplier:add',
        EDIT: 'supplier:edit',
        DELETE: 'supplier:delete',
        SAVE: 'supplier:save',
        CANCEL: 'supplier:cancel',
        SUCCESS: 'supplier:success',
        LOADING: 'supplier:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Proveedores'}
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
                />
            </DxDialog>
        </div>
    );
};
