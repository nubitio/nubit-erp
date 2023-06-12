import {useEffect} from "react";
import Supplier from "../Supplier";

export const SupplierPage = () => {
    const container = "wrapper"
    const supplier = new Supplier(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            setTimeout(() => {
                supplier.render()
                alreadyRender = true
            }, 1000)
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
