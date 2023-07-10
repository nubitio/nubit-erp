import "./CashboxPage.sass";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {CashboxFields} from "./CashboxFields";

export const CashboxPage = () => {
    const container = "wrapper";
    const fields = CashboxFields();

    const API_URL = Constants.API_BASE_URL + 'sales';

    const EVENTS = {
        ADD: 'cashbox:add',
        EDIT: 'cashbox:edit',
        DELETE: 'cashbox:delete',
        SAVE: 'cashbox:save',
        CANCEL: 'cashbox:cancel',
        SUCCESS: 'cashbox:success',
        LOADING: 'cashbox:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Registro de caja',
                url: API_URL,
                allowAdd: true,
                fields: fields,
                events: EVENTS,
            }}>
                <DxDataGrid options={{
                    title: 'Detalle',
                    url: API_URL,
                    fields: fields,
                    events: EVENTS,
                }}/>
            </DxDataGrid>
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
