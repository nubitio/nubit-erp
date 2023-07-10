import {useEffect} from "react";
import Specialty from "../Specialty";
import "./Speciality.scss";
import {EmployeeFields} from "../Employee/EmployeeFields";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {SpecialtyFields} from "./SpecialtyFields";

export const SpecialtyPage = () => {
    const container = "wrapper";
    const fields = SpecialtyFields();

    const API_URL = Constants.API_BASE_URL + 'specialties';

    const EVENTS = {
        ADD: 'specialty:add',
        EDIT: 'specialty:edit',
        DELETE: 'specialty:delete',
        SAVE: 'specialty:save',
        CANCEL: 'specialty:cancel',
        SUCCESS: 'specialty:success',
        LOADING: 'specialty:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Especialidades médicas'}
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
