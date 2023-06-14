import {useEffect} from "react";
import Patient from "../Patient";
import "./PatientPage.scss";

export const PatientPage = () => {
    const container = "wrapper"
    const patient = new Patient(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            patient.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
