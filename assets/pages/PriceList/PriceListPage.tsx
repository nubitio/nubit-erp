import {useEffect} from "react";
import PriceList from "../PriceList";
import "./PriceListPage.scss";
import {SupplierFields} from "../Supplier/SupplierFields";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {PriceListFields} from "./PriceListFields";

export const PriceListPage = () => {
    const container = "wrapper";
    const fields = PriceListFields();

    const API_URL = Constants.API_BASE_URL + 'price-lists';

    const EVENTS = {
        ADD: 'price-list:add',
        EDIT: 'price-list:edit',
        DELETE: 'price-list:delete',
        SAVE: 'price-list:save',
        CANCEL: 'price-list:cancel',
        SUCCESS: 'price-list:success',
        LOADING: 'price-list:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Tarifas',
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
