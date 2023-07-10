import "./WarehousePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {WarehouseFields} from "./WarehouseFields";

export const WarehousePage = () => {
    const container = "wrapper";
    const fields = WarehouseFields();

    const API_URL = Constants.API_BASE_URL + 'warehouses';

    const EVENTS = {
        ADD: 'warehouse:add',
        EDIT: 'warehouse:edit',
        DELETE: 'warehouse:delete',
        SAVE: 'warehouse:save',
        CANCEL: 'warehouse:cancel',
        SUCCESS: 'warehouse:success',
        LOADING: 'warehouse:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid
                title={'Almacen'}
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
