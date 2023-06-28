import Category from "../Category";
import "./Category.scss";
import {categoryFields} from "./CategoryFields";
import {DxDataGrid} from "../../core/datagrid/DxDataGrid";
import {DxForm} from "../../core/form/DxForm";
import {FormPopup} from "../../components";
import {useCallback, useState} from "react";
import {bus} from "../../core/event/EventBus";

export const CategoryPage = () => {
    const container = "wrapper";
    const fields = categoryFields();
    const [popupVisible, setPopupVisible] = useState(false);
    const changePopupVisibility = useCallback(() => {
        setPopupVisible(!popupVisible);
    }, [popupVisible]);

    bus.on(Category.EVENTS.ADD, (payload: any) => {
        setPopupVisible(true)
    });

    bus.on(Category.EVENTS.EDIT, (payload: any) => {
        setPopupVisible(true)
    });

    bus.on(Category.EVENTS.DELETE, (payload: any) => {
        alert('delete')
    });

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
            <FormPopup title='Categoria' visible={popupVisible} setVisible={changePopupVisibility}>
                <DxForm options={{
                    fields: fields,
                    events: Category.EVENTS,
                }}/>
            </FormPopup>
        </div>
    );
};
