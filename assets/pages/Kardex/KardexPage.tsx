import "./KardexPage.scss";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {Constants} from "../../core/common/Constants";
import {KardexDetailFields, KardexFields} from "./KardexFields";
import {useRef} from "react";

export const KardexPage = () => {
    const gridRef = useRef<DxDataGridEvents>(null);
    const container = "wrapper"
    const fields = KardexFields();
    const detailFields = KardexDetailFields();

    const API_URL = Constants.API_BASE_URL + "kardex";

    const toolbar = [
        {
            text: "Kardex",
            icon: "fas fa-file-invoice-dollar",
            type: "success",
            onClick: () => {
                const selectedRow = gridRef.current?.getSelectedRow();
                if (selectedRow) {
                    window.open("/kardex/report/" + selectedRow.product_id, "_blank");
                }
            }
        }
    ];

    return (
        <div id={container}>
            <DxDataGrid
                ref={gridRef}
                title={"Kardex"}
                toolbar={toolbar}
                url={API_URL}
                detailUrl='kardex/{id}/detail'
                fields={fields}
                detailFields={detailFields}
            />
        </div>
    );
};
