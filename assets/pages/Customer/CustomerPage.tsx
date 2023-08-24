import "./CustomerPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {CustomerFields} from "./CustomerFields";
import {useRef} from "react";

export const CustomerPage = () => {
    const formRef = useRef<DxFormEvents|null>(null);
    const container = "wrapper";
    const fields = CustomerFields(formRef.current);

    return (
        <div id={container}>
            <DxDataGrid
                title={'Clientes'}
                url={CustomerPage.API_URL}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                events={CustomerPage.EVENTS}
            />
            <DxDialog
                events={CustomerPage.EVENTS}
            >
                <DxForm
                    ref={formRef}
                    url={CustomerPage.API_URL}
                    fields={fields}
                    events={CustomerPage.EVENTS}
                />
            </DxDialog>
        </div>
    );
};

CustomerPage.EVENTS = {
    ADD: 'customer:add',
    EDIT: 'customer:edit',
    DELETE: 'customer:delete',
    SAVE: 'customer:save',
    CANCEL: 'customer:cancel',
    SUCCESS: 'customer:success',
    LOADING: 'customer:loading',
}

CustomerPage.API_URL = Constants.API_BASE_URL + 'customers';
