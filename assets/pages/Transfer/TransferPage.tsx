import {useEffect} from "react";
import Transfer from "../Transfer";
import "./TransferPage.scss";
import {SupplierFields} from "../Supplier/SupplierFields";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {TransferFields} from "./TransferFields";

export const TransferPage = () => {
    const container = "wrapper";
    const fields = TransferFields();

    const API_URL = Constants.API_BASE_URL + 'transfers';

    const EVENTS = {
        ADD: 'transfer:add',
        EDIT: 'transfer:edit',
        DELETE: 'transfer:delete',
        SAVE: 'transfer:save',
        CANCEL: 'transfer:cancel',
        SUCCESS: 'transfer:success',
        LOADING: 'transfer:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Transferencias'}
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
