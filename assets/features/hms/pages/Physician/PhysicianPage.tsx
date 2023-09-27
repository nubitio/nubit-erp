import "./PhysicianPage.scss";
import {Constants} from "../../../../core/common/Constants";
import {DxDataGrid} from "../../../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../../../core/dialog/DxDialog";
import {DxForm} from "../../../../core/form/DxForm";
import {PhysicianFields} from "./PhysicianFields";

export const PhysicianPage = () => {
    const container = "wrapper";
    const fields = PhysicianFields();

    const API_URL = Constants.API_BASE_URL + 'physicians';

    const EVENTS = {
        ADD: 'physician:add',
        EDIT: 'physician:edit',
        DELETE: 'physician:delete',
        SAVE: 'physician:save',
        CANCEL: 'physician:cancel',
        SUCCESS: 'physician:success',
        LOADING: 'physician:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Médicos'}
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
