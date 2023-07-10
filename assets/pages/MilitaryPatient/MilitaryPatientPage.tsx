import {useEffect} from "react";
import MilitaryPatient from "../MilitaryPatient";
import "./MilitaryPatientPage.scss";
import {ConsultationFields} from "../Consultation/ConsultationFields";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {MilitaryPatientFields} from "./MilitaryPatientFields";

export const MilitaryPatientPage = () => {
    const container = "wrapper";
    const fields = MilitaryPatientFields();

    const API_URL = Constants.API_BASE_URL + 'military-patients';

    const EVENTS = {
        ADD: 'military-patient:add',
        EDIT: 'military-patient:edit',
        DELETE: 'military-patient:delete',
        SAVE: 'military-patient:save',
        CANCEL: 'military-patient:cancel',
        SUCCESS: 'military-patient:success',
        LOADING: 'military-patient:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Pacientes militares'}
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
