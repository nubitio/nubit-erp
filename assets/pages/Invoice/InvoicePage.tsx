import {useEffect} from "react";
import Invoice from "../Invoice";
import "./InvoicePage.scss";

export const InvoicePage = () => {
    const container = "wrapper"
    const invoice = new Invoice(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            invoice.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
