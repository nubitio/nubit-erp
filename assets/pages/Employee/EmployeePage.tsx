import {useEffect} from "react";
import Employee from "../Employee";
import "./EmployeePage.scss";

export const EmployeePage = () => {
    const container = "wrapper"
    const employee = new Employee(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            employee.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
