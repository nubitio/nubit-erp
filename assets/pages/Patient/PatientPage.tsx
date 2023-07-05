import "./PatientPage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {PatientFields} from "./PatientFields";

export const PatientPage = () => {
    const container = "wrapper";
    const fields = PatientFields();

    const API_URL = Constants.API_BASE_URL + 'patients';

    const EVENTS = {
        ADD: 'patient:add',
        EDIT: 'patient:edit',
        DELETE: 'patient:delete',
        SAVE: 'patient:save',
        CANCEL: 'patient:cancel',
        SUCCESS: 'patient:success',
        LOADING: 'patient:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Pacientes',
                url: API_URL,
                allowAdd: true,
                allowEdit: true,
                allowDelete: true,
                fields: fields,
                events: EVENTS,
            }}/>
            <DxDialog options={{
                events: EVENTS,
            }}>
                <DxForm options={{
                    url: API_URL,
                    fields: fields,
                    events: EVENTS,
                }}/>
            </DxDialog>
        </div>
    );
};
