import Category from "../Category";
import "./Category.scss";
import {categoryFields} from "./CategoryFields";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";

export const CategoryPage = () => {
    const container = "wrapper";
    const fields = categoryFields();

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Categorias',
                url: Category.API_URL,
                method: 'get',
                allowAdd: true,
                allowEdit: true,
                allowDelete: true,
                fields: fields,
                events: Category.EVENTS,
            }}/>
        </div>
    );
};
