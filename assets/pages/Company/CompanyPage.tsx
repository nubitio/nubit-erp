import "./CompanyPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {CompanyFields} from "./CompanyFields";

export const CompanyPage = () => {
    const container = "wrapper";
    const fields = CompanyFields();

    const API_URL = Constants.API_BASE_URL + 'companies';

    const EVENTS = {
        ADD: 'company:add',
        EDIT: 'company:edit',
        DELETE: 'company:delete',
        SAVE: 'company:save',
        CANCEL: 'company:cancel',
        SUCCESS: 'company:success',
        LOADING: 'company:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Empresas'}
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
