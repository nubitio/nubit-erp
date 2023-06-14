import {useEffect} from "react";
import Department from "../Department";
import "./DepartmentPage.scss";

export const DepartmentPage = () => {
    const container = "wrapper"
    const department = new Department(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            department.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
