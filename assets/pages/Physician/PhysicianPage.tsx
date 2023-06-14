import {useEffect} from "react";
import Physician from "../Physician";
import "./PhysicianPage.scss";

export const PhysicianPage = () => {
    const container = "wrapper"
    const physician = new Physician(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            physician.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
