import {useEffect} from "react";
import ConsultationSale from "../ConsultationSale";
import "./ConsultationSalePage.scss";

export const ConsultationSalePage = () => {
    const container = "wrapper"
    const consultationSale = new ConsultationSale(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            consultationSale.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
