import "./PatientTypePage.scss"
import {Constants} from "../../../../core/common/Constants";
import {DxDataGrid} from "../../../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../../../core/dialog/DxDialog";
import {DxForm} from "../../../../core/form/DxForm";
import {PatientTypeFields} from "./PatientTypeFields";

export const  PatientTypePage = () => {
    const container = "wrapper";
    const fields = PatientTypeFields();
    const API_URL = Constants.API_BASE_URL + 'patient-types';
    const EVENTS = {
        ADD: 'patient-type:add',
        EDIT: 'patient-type:edit',
        DELETE: 'patient-type:delete',
        SAVE: 'patient-type:save',
        CANCEL: 'patient-type:cancel',
        SUCCESS: 'patient-type:success',
        LOADING: 'patient-type:loading',
    }
    return (
        <div>
            <div id={container}>
                <DxDataGrid
                    title={'Tipos Pacientes'}
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
        </div>)
        ;
};
