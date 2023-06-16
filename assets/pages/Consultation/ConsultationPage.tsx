import {useEffect} from "react";
import Consultation from "../Consultation";
import "./ConsultationPage.scss";

export const ConsultationPage = () => {
    const container = "wrapper"
    const consultation = new Consultation(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            consultation.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
