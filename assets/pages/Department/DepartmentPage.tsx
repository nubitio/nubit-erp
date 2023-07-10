import {useEffect} from "react";
import Department from "../Department";
import "./DepartmentPage.scss";
import {InvoiceFields} from "../Invoice/InvoiceFields";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {DepartmentFields} from "./DepartmentFields";

export const DepartmentPage = () => {
    const container = "wrapper";
    const fields = DepartmentFields();

    const API_URL = Constants.API_BASE_URL + 'departments';

    const EVENTS = {
        ADD: 'department:add',
        EDIT: 'department:edit',
        DELETE: 'department:delete',
        SAVE: 'department:save',
        CANCEL: 'department:cancel',
        SUCCESS: 'department:success',
        LOADING: 'department:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Departamentos laborales'}
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
