import "./RecipePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {RecipeDetailFields, RecipeFields} from "./RecipeFields";
import {useEffect, useRef} from "react";
import {bus} from "../../core/event/EventBus";
import {httpClient} from "../../core/http/HttpClient";

export const RecipePage = () => {
    const container = "wrapper";
    const fields = RecipeFields();
    const detailFields = RecipeDetailFields();
    const formRef = useRef<DxFormEvents>(null)

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

    let alreadySetup = false;

    const setupObservers = () => {
        bus.on(EVENTS.EDIT, (payload: any) => {
            httpClient.get<any>(`${API_URL}/${payload.row.id}/details`).subscribe((response) => {
                formRef.current?.setDetailData(response.data);
            })
        })
    }

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

    return (
        <div id={container}>
            <DxDataGrid
                title={'Recetas'}
                url={API_URL}
                detailUrl={API_URL + '/{id}/details'}
                allowAdd={true}
                allowEdit={true}
                allowDelete={true}
                fields={fields}
                detailFields={detailFields}
                events={EVENTS}
            />
            <DxDialog
                width={800}
                height={500}
                events={EVENTS}
            >
                <DxForm
                    ref={formRef}
                    url={API_URL}
                    fields={fields}
                    detailFields={detailFields}
                    events={EVENTS}
                />
            </DxDialog>
        </div>
    );
};
