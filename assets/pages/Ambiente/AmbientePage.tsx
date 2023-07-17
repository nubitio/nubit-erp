import "./AmbientePage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {Constants} from "../../core/common/Constants";
import {AmbienteFields} from "./AmbienteFields";

export const AmbientePage = () => {
    const container = "wrapper";
    const fields = AmbienteFields();

    const API_URL = Constants.API_BASE_URL + 'ambientes';

    const EVENTS = {
        ADD: 'ambiente:add',
        EDIT: 'ambiente:edit',
        DELETE: 'ambiente:delete',
        SAVE: 'ambiente:save',
        CANCEL: 'ambiente:cancel',
        SUCCESS: 'ambiente:success',
        LOADING: 'ambiente:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                    title={'Ambiente'}
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
