import "./KardexPage.scss";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {Constants} from "../../core/common/Constants";
import {KardexFields} from "./KardexFields";

export const KardexPage = () => {
    const container = "wrapper"
    const fields = KardexFields();

    const API_URL = Constants.API_BASE_URL + "kardex";

    return (
        <div id={container}>
            <DxDataGrid
                title={"Kardex"}
                url={API_URL}
                fields={fields}
            />
        </div>
    );
};
