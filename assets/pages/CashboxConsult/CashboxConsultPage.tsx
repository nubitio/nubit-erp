import "./CashboxConsult.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {CashboxConsultFields} from "./CashboxFields";

export const CashboxConsultPage = () => {
    const container = "wrapper";
    const fields = CashboxConsultFields();

    const API_URL = "";

    const EVENTS = {}

    return (
        <div id={container}>
            <DxDataGrid
                title={'Consulta Caja'}
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
