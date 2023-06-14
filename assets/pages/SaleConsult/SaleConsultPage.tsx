import {useEffect} from "react";
import SaleConsult from "../SaleConsult";
import "./SaleConsultPage.scss";

export const SaleConsultPage = () => {
    const container = "wrapper"
    const saleConsult = new SaleConsult(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            saleConsult.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
