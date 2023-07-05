import "./CreditNotePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {CreditNoteFields} from "./CreditNoteFields";

export const CreditNotePage = () => {
    const container = "wrapper";
    const fields = CreditNoteFields();

    const API_URL = Constants.API_BASE_URL + 'credit-notes';

    const EVENTS = {
        ADD: 'credit-note:add',
        EDIT: 'credit-note:edit',
        DELETE: 'credit-note:delete',
        SAVE: 'credit-note:save',
        CANCEL: 'credit-note:cancel',
        SUCCESS: 'credit-note:success',
        LOADING: 'credit-note:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Notas de crédito',
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
