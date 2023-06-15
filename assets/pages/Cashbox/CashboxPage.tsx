import {useEffect} from "react";
import Cashbox from "../Cashbox";
import "./CashboxPage.sass";

export const CashboxPage = () => {
    const container = "wrapper"
    const cashbox = new Cashbox(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            cashbox.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
