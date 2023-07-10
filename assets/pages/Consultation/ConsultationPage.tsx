import {useEffect} from "react";
import Consultation from "../Consultation";
import "./ConsultationPage.scss";
import {PhysicianFields} from "../Physician/PhysicianFields";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {ConsultationFields} from "./ConsultationFields";

export const ConsultationPage = () => {
    const container = "wrapper";
    const fields = ConsultationFields();

    const API_URL = Constants.API_BASE_URL + 'consultations';

    const EVENTS = {
        ADD: 'consultation:add',
        EDIT: 'consultation:edit',
        DELETE: 'consultation:delete',
        SAVE: 'consultation:save',
        CANCEL: 'consultation:cancel',
        SUCCESS: 'consultation:success',
        LOADING: 'consultation:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Consultas médicas'}
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
