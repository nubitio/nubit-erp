import {useEffect} from "react";
import PatientType from "../PatientType";
import "./PatientTypePage.scss";

export const PatientTypePage = () => {
    const container = "wrapper"
    const patientType = new PatientType(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            patientType.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
