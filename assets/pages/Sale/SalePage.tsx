import {useEffect} from "react";
import Sale from "../Sale";
import "./SalePage.scss";

export const SalePage = () => {
    const container = "wrapper"
    const sale = new Sale(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            sale.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
