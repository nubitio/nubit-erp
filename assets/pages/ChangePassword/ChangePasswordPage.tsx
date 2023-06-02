import {useEffect} from "react";
import ChangePassword from "../ChangePassword";
import "./ChangePasswordPage.scss";

export const ChangePasswordPage = () => {
    const container = "wrapper"
    const changePassword = new ChangePassword(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            changePassword.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
            </div>
    );
};
