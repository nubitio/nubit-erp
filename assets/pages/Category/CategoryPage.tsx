import Category from "../Category";
import "./Category.scss";
import {dxCategoryFields} from "./CategoryFields";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxForm} from "../../core/form/DxForm";
import {FormPopup} from "../../components";
import {useCallback, useEffect, useState} from "react";
import {bus} from "../../core/event/EventBus";

export const CategoryPage = () => {
    const container = "wrapper";
    const fields = dxCategoryFields();
    const [popupVisible, setPopupVisible] = useState(false);
    const changePopupVisibility = useCallback(() => {
        setPopupVisible(!popupVisible);
    }, [popupVisible]);
    const onSave = useCallback(() => {
        bus.emit(Category.EVENTS.SAVE, {});
    }, []);

    const setupObservers = () => {
        bus.on(Category.EVENTS.ADD, (payload: any) => {
            setPopupVisible(true)
        });

        bus.on(Category.EVENTS.EDIT, (payload: any) => {
            setPopupVisible(true)
        });

        bus.on(Category.EVENTS.DELETE, (payload: any) => {
            alert('delete')
        });
    }

    let alreadySetup = false;

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

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
            <FormPopup title='Categoria' visible={popupVisible} setVisible={changePopupVisibility} onSave={onSave}>
                <DxForm options={{
                    url: Category.API_URL,
                    fields: fields,
                    events: Category.EVENTS,
                }}/>
            </FormPopup>
        </div>
    );
};
