import {useEffect} from "react";
import MilitaryPatient from "../MilitaryPatient";
import "./MilitaryPatientPage.scss";

export const MilitaryPatientPage = () => {
    const container = "wrapper"
    const militaryPatient = new MilitaryPatient(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            militaryPatient.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
