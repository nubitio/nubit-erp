import "./ProductPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxForm} from "../../core/form/DxForm";
import {DxDialog} from "../../core/dialog/DxDialog";
import {Constants} from "../../core/common/Constants";
import {ProductFields} from "./ProductFields";

export const ProductPage = () => {
    const container = "wrapper";
    const fields = ProductFields();

    const API_URL = Constants.API_BASE_URL + 'products';

    const EVENTS = {
        ADD: 'product:add',
        EDIT: 'product:edit',
        DELETE: 'product:delete',
        SAVE: 'product:save',
        CANCEL: 'product:cancel',
        SUCCESS: 'product:success',
        LOADING: 'product:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Productos',
                url: API_URL,
                allowAdd: true,
                allowEdit: true,
                allowDelete: true,
                fields: fields,
                events: EVENTS,
                sort: [{selector: 'descripcion', desc: false}],
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
