import "./PaymentMethodPage.scss";
import {PaymentMethodFields} from "./PaymentMethodFields";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxForm} from "../../core/form/DxForm";
import {DxDialog} from "../../core/dialog/DxDialog";
import {Constants} from "../../core/common/Constants";

export const PaymentMethodPage = () => {
    const container = "wrapper";
    const API_URL = Constants.API_BASE_URL + 'payment-methods';
    const fields = PaymentMethodFields();
    const EVENTS = {
        ADD: 'payment-methods:add',
        EDIT: 'payment-methods:edit',
        DELETE: 'payment-methods:delete',
        SAVE: 'payment-methods:save',
        CANCEL: 'payment-methods:cancel',
        SUCCESS: 'payment-methods:success',
        LOADING: 'payment-methods:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                id={'payment-methods'}
                title={'Medios de pago'}
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
