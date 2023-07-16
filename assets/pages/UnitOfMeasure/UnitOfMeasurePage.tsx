import "./UnitOfMeasure.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {UnitOfMeasureFields} from "./UnitOfMeasureFields";

export const UnitOfMeasurePage = () => {
    const container = "wrapper";
    const fields = UnitOfMeasureFields();

    const API_URL = Constants.API_BASE_URL + 'unit-of-measures';

    const EVENTS = {
        ADD: 'unit-of-measure:add',
        EDIT: 'unit-of-measure:edit',
        DELETE: 'unit-of-measure:delete',
        SAVE: 'unit-of-measure:save',
        CANCEL: 'unit-of-measure:cancel',
        SUCCESS: 'unit-of-measure:success',
        LOADING: 'unit-of-measure:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Unidades de medida'}
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
