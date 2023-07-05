import "./RecipePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm} from "../../core/form/DxForm";
import {RecipeFields} from "./RecipeFields";

export const RecipePage = () => {
    const container = "wrapper";
    const fields = RecipeFields();

    const API_URL = Constants.API_BASE_URL + 'recipes';

    const EVENTS = {
        ADD: 'recipe:add',
        EDIT: 'recipe:edit',
        DELETE: 'recipe:delete',
        SAVE: 'recipe:save',
        CANCEL: 'recipe:cancel',
        SUCCESS: 'recipe:success',
        LOADING: 'recipe:loading',
    }

    return (
        <div id={container}>
            <DxDataGrid options={{
                title: 'Recetas',
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
