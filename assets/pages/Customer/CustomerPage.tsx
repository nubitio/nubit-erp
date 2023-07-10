import "./CustomerPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {CustomerFields} from "./CustomerFields";

export const CustomerPage = () => {
    const container = "wrapper";
    const fields = CustomerFields();

    const API_URL = Constants.API_BASE_URL + 'customers';

    const EVENTS = {
        ADD: 'customer:add',
        EDIT: 'customer:edit',
        DELETE: 'customer:delete',
        SAVE: 'customer:save',
        CANCEL: 'customer:cancel',
        SUCCESS: 'customer:success',
        LOADING: 'customer:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Clientes',
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
