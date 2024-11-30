import "./OperationPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {OperationFields} from "./OperationFields";

export const OperationPage = () => {
    const container = "wrapper";
    const fields = OperationFields();

    const API_URL = Constants.API_BASE_URL + 'operations';

    const EVENTS = {
        ADD: 'operation:add',
        EDIT: 'operation:edit',
        DELETE: 'operation:delete',
        SAVE: 'operation:save',
        CANCEL: 'operation:cancel',
        SUCCESS: 'operation:success',
        LOADING: 'operation:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                id={'operation'}
                title={'Operaciones'}
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
