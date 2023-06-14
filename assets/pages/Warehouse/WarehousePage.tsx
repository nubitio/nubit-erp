import {useEffect} from "react";
import Warehouse from "../Warehouse";
import "./WarehousePage.scss";

export const WarehousePage = () => {
    const container = "wrapper"
    const warehouse = new Warehouse(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            warehouse.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
