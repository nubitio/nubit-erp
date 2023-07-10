import "./PositionPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {PositionFields} from "./PositionFields";

export const PositionPage = () => {
    const container = "wrapper";
    const fields = PositionFields();

    const API_URL = Constants.API_BASE_URL + 'positions';

    const EVENTS = {
        ADD: 'position:add',
        EDIT: 'position:edit',
        DELETE: 'position:delete',
        SAVE: 'position:save',
        CANCEL: 'position:cancel',
        SUCCESS: 'position:success',
        LOADING: 'position:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Posiciones de trabajo'}
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
