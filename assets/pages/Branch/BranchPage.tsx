import "./BranchPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {BranchFields} from "./BranchFields";

export const BranchPage = () => {
    const container = "wrapper";
    const fields = BranchFields();

    const API_URL = Constants.API_BASE_URL + 'branches';

    const EVENTS = {
        ADD: 'branch:add',
        EDIT: 'branch:edit',
        DELETE: 'branch:delete',
        SAVE: 'branch:save',
        CANCEL: 'branch:cancel',
        SUCCESS: 'branch:success',
        LOADING: 'branch:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                id={'branch'}
                title={'Locales'}
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
