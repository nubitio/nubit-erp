import "./PerformancePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {PerformanceFields} from "./PerformanceFields";

export const PerformancePage = () => {
    const container = "wrapper";
    const fields = PerformanceFields();

    const API_URL = Constants.API_BASE_URL + 'performances';

    const EVENTS = {
        ADD: 'performance:add',
        EDIT: 'performance:edit',
        DELETE: 'performance:delete',
        SAVE: 'performance:save',
        CANCEL: 'performance:cancel',
        SUCCESS: 'performance:success',
        LOADING: 'performance:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Rendimiento'}
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
