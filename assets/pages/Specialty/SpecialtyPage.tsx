import {useEffect} from "react";
import Specialty from "../Specialty";
import "./Speciality.scss";

export const SpecialtyPage = () => {
    const container = "wrapper"
    const specialty = new Specialty(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            specialty.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
