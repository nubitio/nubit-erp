import {useEffect} from "react";
import Role from "../Role";
import "./RolePage.scss";

export const RolePage = () => {
    const container = "wrapper"
    const role = new Role(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            role.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
