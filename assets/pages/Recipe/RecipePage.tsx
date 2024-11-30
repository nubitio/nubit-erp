import "./RecipePage.scss";
import {Constants} from "../../core/common/Constants";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxDialog} from "../../core/dialog/DxDialog";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {RecipeDetailFields, RecipeFields} from "./RecipeFields";
import {useEffect, useRef} from "react";
import {httpClient} from "../../core/http/HttpClient";
import {useEvents} from "../../core/event/EventHook";

export const RecipePage = () => {
    const [on, emit] = useEvents();
    const formRef = useRef<DxFormEvents>(null)
    const container = "wrapper";
    const fields = RecipeFields(formRef.current);
    const detailFields = RecipeDetailFields();

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
        on(EVENTS.EDIT, (payload: any) => {
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
                id={'recipe'}
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
