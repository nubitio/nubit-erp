import {useEffect} from "react";
import Supplier from "../Supplier";

export const SupplierPage = () => {
    const supplier = new Supplier('wrapper')
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            supplier.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id="wrapper">
        </div>
    );
};