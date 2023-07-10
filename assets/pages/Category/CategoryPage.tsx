import Category from "../Category";
import "./CategoryPage.scss";
import {dxCategoryFields} from "./CategoryFields";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxForm} from "../../core/form/DxForm";
import {DxDialog} from "../../core/dialog/DxDialog";

export const CategoryPage = () => {
    const container = "wrapper";
    const fields = dxCategoryFields();

    return (
        <div id={container}>
            <DxDataGrid
                title={'Categorias'}
                url={Category.API_URL}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                events={Category.EVENTS}
            />
            <DxDialog
                events={Category.EVENTS}
            >
                <DxForm
                    url={Category.API_URL}
                    fields={fields}
                    events={Category.EVENTS}
                />
            </DxDialog>
        </div>
    );
};
