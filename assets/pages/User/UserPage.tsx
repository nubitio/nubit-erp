import "./UserPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {UserFields} from "./UserFields";

export const UserPage = () => {
    const container = "wrapper";
    const fields = UserFields();

    const API_URL = Constants.API_BASE_URL + 'users';

    const EVENTS = {
        ADD: 'user:add',
        EDIT: 'user:edit',
        DELETE: 'user:delete',
        SAVE: 'user:save',
        CANCEL: 'user:cancel',
        SUCCESS: 'user:success',
        LOADING: 'user:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                id={'user'}
                title={'Usuarios'}
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
                    useIRI={true}
                />
            </DxDialog>
        </div>
    );
};
