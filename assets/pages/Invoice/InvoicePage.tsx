import "./InvoicePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {InvoiceFields} from "./InvoiceFields";

export const InvoicePage = () => {
    const container = "wrapper";
    const fields = InvoiceFields();

    const API_URL = Constants.API_BASE_URL + 'invoices';

    const EVENTS = {
        ADD: 'invoice:add',
        EDIT: 'invoice:edit',
        DELETE: 'invoice:delete',
        SAVE: 'invoice:save',
        CANCEL: 'invoice:cancel',
        SUCCESS: 'invoice:success',
        LOADING: 'invoice:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Comprobantes de pago',
                url: API_URL,
                allowAdd: true,
                allowEdit: true,
                allowDelete: true,
                fields: fields,
                events: EVENTS,
            }}/>
            <DxDialog options={{
                events: EVENTS,
            }}>
                <DxForm options={{
                    url: API_URL,
                    fields: fields,
                    events: EVENTS,
                }}/>
            </DxDialog>
        </div>
    );
};
