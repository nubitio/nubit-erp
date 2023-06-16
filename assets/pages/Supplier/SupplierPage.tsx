import {useEffect} from "react";
import Supplier from "../Supplier";
import "./SupplierPage.scss";

export const SupplierPage = () => {
    const container = "wrapper"
    const supplier = new Supplier(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            supplier.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
