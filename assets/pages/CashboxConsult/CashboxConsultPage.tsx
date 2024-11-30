import "./CashboxConsult.scss";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {CashboxDetailFields, CashboxFields} from "../Cashbox/CashboxFields";
import {Constants} from "../../core/common/Constants";
import {useRef} from "react";
import notify from "devextreme/ui/notify";
import {userInformation} from "../../core/common/UserInformation";

export const CashboxConsultPage = () => {
    const container = "wrapper";
    const fields = CashboxFields();
    const detailFields = CashboxDetailFields();
    const gridRef = useRef<DxDataGridEvents>(null);

    const API_URL = Constants.API_BASE_URL + 'cashboxes';

    const toolbar = [
        {
            text: 'Reporte Simple',
            icon: Constants.ICON_PDF,
            type: 'default',
            onClick: () => {
                let selectedRow = gridRef.current?.getSelectedRow();

                if (!selectedRow) {
                    notify({
                        message: 'Seleccione un registro',
                        type: 'info',
                        displayTime: 3000,
                    }, {position: 'top right'});
                }

                window.open('cashbox/export-new/' + selectedRow.caja_id);
            }
        },
        {
            text: 'Reporte Detallado',
            icon: Constants.ICON_PDF,
            type: 'default',
            onClick: () => {
                let selectedRow = gridRef.current?.getSelectedRow();

                if (!selectedRow) {
                    notify({
                        message: 'Seleccione un registro',
                        type: 'info',
                        displayTime: 3000,
                    }, {position: 'top right'});
                }

                window.open('cashbox-new/report/' + selectedRow.caja_id);
            }
        },
    ]

    return (
        <div id={container}>
            <DxDataGrid
                id={'cashbox-consult'}
                ref={gridRef}
                title={'Reportes de caja'}
                toolbar={toolbar}
                url={API_URL}
                detailUrl={API_URL + '/{id}/details'}
                fields={fields}
                detailFields={detailFields}
                filter={[
                    {
                        field: 'local',
                        operator: '=',
                        value: userInformation().currentBranchId
                    }
                ]}
            />
        </div>
    );
};
