import {useEffect} from "react";
import CashboxConsult from "../CashboxConsult";
import "./CashboxConsult.scss";

export const CashboxConsultPage = () => {
    const container = "wrapper"
    const cashboxConsult = new CashboxConsult(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            cashboxConsult.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
